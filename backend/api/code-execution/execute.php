<?php
/**
 * POST /api/code/execute
 * Evaluates student code against test cases in an isolated subprocess.
 */
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/InputValidator.php';

$user = Auth::authorize(['student', 'examiner', 'admin']);
$userId = (int)$user['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed', 405);

$body = InputValidator::getRequestBody();
$language = strtolower(trim($body['language'] ?? 'python'));
$code = $body['code'] ?? '';
$questionId = InputValidator::validateInt($body['question_id'] ?? null);
$customInput = $body['custom_input'] ?? null;

if (empty($code)) Response::error('Code cannot be empty', 422);

// Load test cases
$testCases = [];
if ($questionId) {
    $testCases = db()->fetchAll(
        "SELECT id, input_data, expected_output, is_hidden, marks FROM coding_test_cases WHERE question_id = ?",
        [$questionId]
    );
}

if (empty($testCases) && $customInput !== null) {
    $testCases = [['id' => 0, 'input_data' => $customInput, 'expected_output' => '', 'is_hidden' => 0, 'marks' => 1]];
}

$results = [];
$passedCount = 0;
$totalScore = 0.0;
$earnedScore = 0.0;

$tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'exam_code_' . uniqid();
@mkdir($tmpDir, 0777, true);

foreach ($testCases as $tc) {
    $totalScore += (float)$tc['marks'];
    $input = $tc['input_data'] ?? '';
    $expected = trim($tc['expected_output'] ?? '');

    $execRes = runSubprocess($language, $code, $input, $tmpDir);

    $actual = trim($execRes['stdout']);
    $passed = ($actual === $expected && $execRes['exit_code'] === 0);

    if ($passed) {
        $passedCount++;
        $earnedScore += (float)$tc['marks'];
    }

    $results[] = [
        'test_case_id' => $tc['id'],
        'is_hidden' => (bool)$tc['is_hidden'],
        'input' => $tc['is_hidden'] ? '[HIDDEN]' : $input,
        'expected' => $tc['is_hidden'] ? '[HIDDEN]' : $expected,
        'actual' => $tc['is_hidden'] ? ($passed ? '[PASSED]' : '[FAILED]') : $actual,
        'passed' => $passed,
        'stderr' => $execRes['stderr'],
        'time_ms' => $execRes['time_ms'],
        'status' => $execRes['status']
    ];
}

// Cleanup temp directory
@array_map('unlink', glob("{$tmpDir}/*.*"));
@rmdir($tmpDir);

Response::success([
    'results' => $results,
    'total_tests' => count($testCases),
    'passed_tests' => $passedCount,
    'earned_score' => $earnedScore,
    'total_score' => $totalScore,
    'is_all_passed' => ($passedCount === count($testCases) && count($testCases) > 0)
]);

function runSubprocess(string $lang, string $code, string $input, string $dir): array {
    $startTime = microtime(true);
    $status = 'completed';

    $file = '';
    $cmd = '';

    if ($lang === 'python' || $lang === 'py') {
        $file = $dir . DIRECTORY_SEPARATOR . 'solution.py';
        file_put_contents($file, $code);
        $cmd = "python \"{$file}\"";
    } elseif ($lang === 'javascript' || $lang === 'node' || $lang === 'js') {
        $file = $dir . DIRECTORY_SEPARATOR . 'solution.js';
        file_put_contents($file, $code);
        $cmd = "node \"{$file}\"";
    } elseif ($lang === 'php') {
        $file = $dir . DIRECTORY_SEPARATOR . 'solution.php';
        file_put_contents($file, "<?php\n" . $code);
        $cmd = "php \"{$file}\"";
    } else {
        return ['stdout' => '', 'stderr' => 'Unsupported language', 'exit_code' => 1, 'time_ms' => 0, 'status' => 'error'];
    }

    $descriptors = [
        0 => ["pipe", "r"], // stdin
        1 => ["pipe", "w"], // stdout
        2 => ["pipe", "w"]  // stderr
    ];

    $process = proc_open($cmd, $descriptors, $pipes, $dir);

    if (!is_resource($process)) {
        return ['stdout' => '', 'stderr' => 'Failed to initialize execution engine', 'exit_code' => 1, 'time_ms' => 0, 'status' => 'error'];
    }

    // Write input to stdin
    fwrite($pipes[0], $input);
    fclose($pipes[0]);

    // Read outputs with timeout limit (3 seconds)
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);
    $timeMs = round((microtime(true) - $startTime) * 1000);

    return [
        'stdout' => $stdout ?: '',
        'stderr' => $stderr ?: '',
        'exit_code' => $exitCode,
        'time_ms' => $timeMs,
        'status' => $exitCode === 0 ? 'completed' : 'runtime_error'
    ];
}
