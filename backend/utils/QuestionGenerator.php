<?php
/**
 * QuestionGenerator.php
 * Automated Question Bank & Intelligent Question Suggester for Examiner Portal
 * - Generates high-quality syllabus-aligned questions based on Subject Name and Topic Details
 * - Supports MCQ, Multiple Correct, True/False, Short Answer, and Coding assessment types
 * - Difficulty scaling (Easy, Medium, Hard, Mixed)
 * - Complete with Distractors, Answer Keys, Explanations, and Coding Test Cases
 */

class QuestionGenerator {

    /**
     * Preset syllabus repository for core subjects
     */
    private static array $knowledgeBase = [
        'Data Structures and Algorithms' => [
            'topics' => ['Arrays & Strings', 'Linked Lists', 'Stacks & Queues', 'Binary Trees & BST', 'Heaps & Priority Queues', 'Graph Algorithms', 'Sorting & Searching', 'Dynamic Programming'],
            'questions' => [
                [
                    'topic' => 'Binary Trees & BST',
                    'difficulty' => 'easy',
                    'type' => 'mcq',
                    'marks' => 1.0,
                    'text' => 'What is the time complexity of searching for an element in a balanced Binary Search Tree (BST) with N nodes?',
                    'options' => [
                        ['text' => 'O(log N)', 'is_correct' => 1],
                        ['text' => 'O(N)', 'is_correct' => 0],
                        ['text' => 'O(1)', 'is_correct' => 0],
                        ['text' => 'O(N log N)', 'is_correct' => 0]
                    ],
                    'explanation' => 'In a balanced BST, each comparison halves the search space, yielding O(log N) average and worst-case time complexity.'
                ],
                [
                    'topic' => 'Binary Trees & BST',
                    'difficulty' => 'medium',
                    'type' => 'mcq',
                    'marks' => 2.0,
                    'text' => 'Which tree traversal order yields values in strictly non-decreasing order for a Binary Search Tree?',
                    'options' => [
                        ['text' => 'In-order Traversal (Left, Root, Right)', 'is_correct' => 1],
                        ['text' => 'Pre-order Traversal (Root, Left, Right)', 'is_correct' => 0],
                        ['text' => 'Post-order Traversal (Left, Right, Root)', 'is_correct' => 0],
                        ['text' => 'Level-order Traversal (BFS)', 'is_correct' => 0]
                    ],
                    'explanation' => 'In-order traversal visits nodes in Left -> Root -> Right sequence, which inherently corresponds to sorted order in a BST.'
                ],
                [
                    'topic' => 'Graph Algorithms',
                    'difficulty' => 'medium',
                    'type' => 'mcq',
                    'marks' => 2.0,
                    'text' => 'Which of the following shortest path algorithms is guaranteed to work on graphs containing negative edge weights without negative cycles?',
                    'options' => [
                        ['text' => 'Bellman-Ford Algorithm', 'is_correct' => 1],
                        ['text' => 'Dijkstra\'s Algorithm', 'is_correct' => 0],
                        ['text' => 'Breadth-First Search (BFS)', 'is_correct' => 0],
                        ['text' => 'Prim\'s Minimum Spanning Tree', 'is_correct' => 0]
                    ],
                    'explanation' => 'Bellman-Ford relaxes all edges V-1 times and can safely handle graphs with negative edge weights and detect negative cycles.'
                ],
                [
                    'topic' => 'Sorting & Searching',
                    'difficulty' => 'medium',
                    'type' => 'mcq',
                    'marks' => 2.0,
                    'text' => 'What is the worst-case time complexity of Standard QuickSort when the pivot is always chosen as the first element on an already sorted array?',
                    'options' => [
                        ['text' => 'O(N²)', 'is_correct' => 1],
                        ['text' => 'O(N log N)', 'is_correct' => 0],
                        ['text' => 'O(N)', 'is_correct' => 0],
                        ['text' => 'O(log N)', 'is_correct' => 0]
                    ],
                    'explanation' => 'When already sorted and first element is pivot, partitions become completely unbalanced of sizes 0 and N-1, degrading to O(N²).'
                ],
                [
                    'topic' => 'Stacks & Queues',
                    'difficulty' => 'easy',
                    'type' => 'true_false',
                    'marks' => 1.0,
                    'text' => 'A Queue data structure strictly follows the LIFO (Last In First Out) principle.',
                    'options' => [
                        ['text' => 'False (Queue follows FIFO — First In First Out)', 'is_correct' => 1],
                        ['text' => 'True', 'is_correct' => 0]
                    ],
                    'explanation' => 'Queues follow FIFO (First In First Out). Stacks follow LIFO (Last In First Out).'
                ],
                [
                    'topic' => 'Dynamic Programming',
                    'difficulty' => 'hard',
                    'type' => 'mcq',
                    'marks' => 3.0,
                    'text' => 'What are the two essential properties required for a computational problem to be effectively solved using Dynamic Programming?',
                    'options' => [
                        ['text' => 'Optimal Substructure and Overlapping Subproblems', 'is_correct' => 1],
                        ['text' => 'Greedy Choice Property and Divide and Conquer', 'is_correct' => 0],
                        ['text' => 'Linear Time Bound and Complete Tree State', 'is_correct' => 0],
                        ['text' => 'Memoization Table and Breadth-First Expansion', 'is_correct' => 0]
                    ],
                    'explanation' => 'Dynamic Programming requires Optimal Substructure (optimal solution contains optimal sub-solutions) and Overlapping Subproblems (subproblems recur multiple times).'
                ],
                [
                    'topic' => 'Arrays & Strings',
                    'difficulty' => 'easy',
                    'type' => 'short_answer',
                    'marks' => 2.0,
                    'text' => 'Explain the fundamental difference between an Array and a Singly Linked List in terms of memory layout and element insertion at the beginning.',
                    'options' => [],
                    'explanation' => 'Arrays require contiguous memory with O(N) insertion at beginning due to shifting. Linked Lists use dynamic heap nodes with pointers, achieving O(1) insertion at head.'
                ],
                [
                    'topic' => 'Algorithm Implementation',
                    'difficulty' => 'medium',
                    'type' => 'coding',
                    'marks' => 5.0,
                    'text' => 'Write a function <code>reverse_array(arr)</code> that takes a list of integers and reverses it in-place using two pointers.',
                    'options' => [],
                    'test_cases' => [
                        ['input' => '[1, 2, 3, 4, 5]', 'output' => '[5, 4, 3, 2, 1]', 'is_hidden' => 0],
                        ['input' => '[10, 20]', 'output' => '[20, 10]', 'is_hidden' => 0],
                        ['input' => '[7]', 'output' => '[7]', 'is_hidden' => 1]
                    ],
                    'explanation' => 'Use left pointer at 0 and right pointer at len(arr)-1, swap elements and advance pointers until they meet.'
                ]
            ]
        ],

        'Database Management Systems' => [
            'topics' => ['Relational Model', 'SQL Queries & Joins', 'Normalization (1NF to BCNF)', 'Transactions & ACID', 'Concurrency & Locking', 'Indexing & B-Trees'],
            'questions' => [
                [
                    'topic' => 'Normalization (1NF to BCNF)',
                    'difficulty' => 'medium',
                    'type' => 'mcq',
                    'marks' => 2.0,
                    'text' => 'A relational table is said to be in Third Normal Form (3NF) if it is in 2NF and has NO:',
                    'options' => [
                        ['text' => 'Transitive dependencies between non-prime attributes', 'is_correct' => 1],
                        ['text' => 'Partial dependencies on candidate keys', 'is_correct' => 0],
                        ['text' => 'Multivalued attributes in single columns', 'is_correct' => 0],
                        ['text' => 'Composite primary keys', 'is_correct' => 0]
                    ],
                    'explanation' => '3NF eliminates transitive dependencies (X -> Y and Y -> Z, where Z depends on non-prime attribute Y).'
                ],
                [
                    'topic' => 'Transactions & ACID',
                    'difficulty' => 'easy',
                    'type' => 'mcq',
                    'marks' => 1.0,
                    'text' => 'Which ACID property guarantees that all database updates within a transaction are completed or none are applied?',
                    'options' => [
                        ['text' => 'Atomicity ("All or Nothing")', 'is_correct' => 1],
                        ['text' => 'Consistency', 'is_correct' => 0],
                        ['text' => 'Isolation', 'is_correct' => 0],
                        ['text' => 'Durability', 'is_correct' => 0]
                    ],
                    'explanation' => 'Atomicity ensures transactions execute as single indivisible units of work.'
                ],
                [
                    'topic' => 'SQL Queries & Joins',
                    'difficulty' => 'medium',
                    'type' => 'mcq',
                    'marks' => 2.0,
                    'text' => 'Which SQL JOIN returns all records from the left table and matched records from the right table, filling NULL for unmatched right rows?',
                    'options' => [
                        ['text' => 'LEFT OUTER JOIN', 'is_correct' => 1],
                        ['text' => 'INNER JOIN', 'is_correct' => 0],
                        ['text' => 'FULL OUTER JOIN', 'is_correct' => 0],
                        ['text' => 'CROSS JOIN', 'is_correct' => 0]
                    ],
                    'explanation' => 'LEFT OUTER JOIN guarantees preservation of every tuple from the left relation.'
                ],
                [
                    'topic' => 'Concurrency & Locking',
                    'difficulty' => 'hard',
                    'type' => 'mcq',
                    'marks' => 3.0,
                    'text' => 'In the Two-Phase Locking (2PL) protocol, what guarantees conflict serializability of concurrent transactions?',
                    'options' => [
                        ['text' => 'A transaction cannot request new locks once it begins releasing locks (Shrinking phase)', 'is_correct' => 1],
                        ['text' => 'Shared locks can be converted to exclusive locks at any time', 'is_correct' => 0],
                        ['text' => 'Deadlocks can never occur under rigorous 2PL', 'is_correct' => 0],
                        ['text' => 'All locks are released simultaneously upon abort', 'is_correct' => 0]
                    ],
                    'explanation' => '2PL ensures serializability because no lock can be acquired after the first lock release (growing phase followed strictly by shrinking phase).'
                ],
                [
                    'topic' => 'Indexing & B-Trees',
                    'difficulty' => 'easy',
                    'type' => 'true_false',
                    'marks' => 1.0,
                    'text' => 'In a B+ Tree index, actual record pointers/data are stored exclusively in the leaf nodes, while internal nodes only store navigation keys.',
                    'options' => [
                        ['text' => 'True', 'is_correct' => 1],
                        ['text' => 'False', 'is_correct' => 0]
                    ],
                    'explanation' => 'True. B+ trees store all actual data pointers in linked leaf nodes, enabling efficient range scans.'
                ]
            ]
        ],

        'Operating Systems' => [
            'topics' => ['Processes & Threads', 'CPU Scheduling', 'Deadlocks', 'Synchronization & Semaphores', 'Memory Management & Paging', 'Virtual Memory'],
            'questions' => [
                [
                    'topic' => 'CPU Scheduling',
                    'difficulty' => 'easy',
                    'type' => 'mcq',
                    'marks' => 1.0,
                    'text' => 'Which CPU scheduling algorithm can suffer from the "Convoy Effect" when a long CPU-bound process occupies the processor ahead of I/O-bound processes?',
                    'options' => [
                        ['text' => 'First-Come First-Served (FCFS)', 'is_correct' => 1],
                        ['text' => 'Round Robin (RR)', 'is_correct' => 0],
                        ['text' => 'Shortest Job First (Preemptive)', 'is_correct' => 0],
                        ['text' => 'Multi-Level Feedback Queue', 'is_correct' => 0]
                    ],
                    'explanation' => 'In non-preemptive FCFS, short processes get stuck waiting behind a CPU-heavy process, creating the convoy effect.'
                ],
                [
                    'topic' => 'Deadlocks',
                    'difficulty' => 'medium',
                    'type' => 'mcq',
                    'marks' => 2.0,
                    'text' => 'Which of the following is NOT one of Coffman\'s four necessary conditions for a system deadlock to occur?',
                    'options' => [
                        ['text' => 'Preemption of held resources', 'is_correct' => 1],
                        ['text' => 'Mutual Exclusion', 'is_correct' => 0],
                        ['text' => 'Hold and Wait', 'is_correct' => 0],
                        ['text' => 'Circular Wait', 'is_correct' => 0]
                    ],
                    'explanation' => 'The condition is NO PREEMPTION. If preemption is allowed, deadlocks cannot persist.'
                ],
                [
                    'topic' => 'Memory Management & Paging',
                    'difficulty' => 'medium',
                    'type' => 'mcq',
                    'marks' => 2.0,
                    'text' => 'In virtual memory systems with paging, what is the phenomenon called where excessive page fault frequency leads to the system spending more time swapping than executing instructions?',
                    'options' => [
                        ['text' => 'Thrashing', 'is_correct' => 1],
                        ['text' => 'External Fragmentation', 'is_correct' => 0],
                        ['text' => 'Belady\'s Anomaly', 'is_correct' => 0],
                        ['text' => 'Segmentation Fault', 'is_correct' => 0]
                    ],
                    'explanation' => 'Thrashing occurs when active working sets exceed physical memory, resulting in non-stop page fault handling.'
                ],
                [
                    'topic' => 'Synchronization & Semaphores',
                    'difficulty' => 'hard',
                    'type' => 'mcq',
                    'marks' => 3.0,
                    'text' => 'In Dijkstra\'s counting semaphore implementation, what happens when a process executes the <code>wait()</code> (or <code>P()</code>) operation on a semaphore with value 0?',
                    'options' => [
                        ['text' => 'The process is suspended / blocked and placed on the semaphore waiting queue', 'is_correct' => 1],
                        ['text' => 'The semaphore increments by 1 and continues immediately', 'is_correct' => 0],
                        ['text' => 'A hardware interrupt restarts the operating system kernel', 'is_correct' => 0],
                        ['text' => 'The process terminates with an illegal access exception', 'is_correct' => 0]
                    ],
                    'explanation' => 'wait(S) decrements S; if S <= 0, the calling thread blocks until another thread calls signal(S).'
                ]
            ]
        ],

        'Computer Networks' => [
            'topics' => ['OSI & TCP/IP Model', 'IP Addressing & Subnetting', 'Routing Protocols', 'TCP vs UDP', 'DNS, HTTP, HTTPS', 'Network Security'],
            'questions' => [
                [
                    'topic' => 'TCP vs UDP',
                    'difficulty' => 'easy',
                    'type' => 'mcq',
                    'marks' => 1.0,
                    'text' => 'Which transport layer protocol provides connection-oriented, reliable, ordered, and flow-controlled data transmission?',
                    'options' => [
                        ['text' => 'TCP (Transmission Control Protocol)', 'is_correct' => 1],
                        ['text' => 'UDP (User Datagram Protocol)', 'is_correct' => 0],
                        ['text' => 'IP (Internet Protocol)', 'is_correct' => 0],
                        ['text' => 'ICMP (Internet Control Message Protocol)', 'is_correct' => 0]
                    ],
                    'explanation' => 'TCP establishes sessions via 3-way handshake and guarantees delivery and order with sequence numbers and acknowledgments.'
                ],
                [
                    'topic' => 'IP Addressing & Subnetting',
                    'difficulty' => 'medium',
                    'type' => 'mcq',
                    'marks' => 2.0,
                    'text' => 'For an IPv4 subnet configured with prefix <code>192.168.10.0/26</code>, what is the total number of usable host addresses?',
                    'options' => [
                        ['text' => '62 usable hosts', 'is_correct' => 1],
                        ['text' => '64 usable hosts', 'is_correct' => 0],
                        ['text' => '30 usable hosts', 'is_correct' => 0],
                        ['text' => '126 usable hosts', 'is_correct' => 0]
                    ],
                    'explanation' => 'A /26 subnet leaves 32-26 = 6 host bits (2^6 = 64 total addresses). Subtracting 2 (network and broadcast) yields 62 usable host addresses.'
                ],
                [
                    'topic' => 'DNS, HTTP, HTTPS',
                    'difficulty' => 'medium',
                    'type' => 'mcq',
                    'marks' => 2.0,
                    'text' => 'Which protocol is primarily used by DNS resolution for fast, low-overhead domain name query and response packets?',
                    'options' => [
                        ['text' => 'UDP on Port 53', 'is_correct' => 1],
                        ['text' => 'TCP on Port 80', 'is_correct' => 0],
                        ['text' => 'SSH on Port 22', 'is_correct' => 0],
                        ['text' => 'FTP on Port 21', 'is_correct' => 0]
                    ],
                    'explanation' => 'Standard DNS queries use UDP port 53 because of minimal latency and stateless request-response communication.'
                ]
            ]
        ],

        'Web Technologies' => [
            'topics' => ['HTML5 & Semantic Markup', 'CSS3 & Flexbox/Grid', 'JavaScript ES6+', 'RESTful API Architecture', 'Asynchronous JS (Promises, Async/Await)'],
            'questions' => [
                [
                    'topic' => 'JavaScript ES6+',
                    'difficulty' => 'easy',
                    'type' => 'mcq',
                    'marks' => 1.0,
                    'text' => 'Which keyword in modern JavaScript declares a block-scoped variable that cannot be reassigned after declaration?',
                    'options' => [
                        ['text' => 'const', 'is_correct' => 1],
                        ['text' => 'var', 'is_correct' => 0],
                        ['text' => 'let', 'is_correct' => 0],
                        ['text' => 'static', 'is_correct' => 0]
                    ],
                    'explanation' => 'const creates an immutable block-scoped binding.'
                ],
                [
                    'topic' => 'RESTful API Architecture',
                    'difficulty' => 'medium',
                    'type' => 'mcq',
                    'marks' => 2.0,
                    'text' => 'In standard RESTful design, which HTTP method is considered idempotent and used for completely replacing a resource at a given URI?',
                    'options' => [
                        ['text' => 'PUT', 'is_correct' => 1],
                        ['text' => 'POST', 'is_correct' => 0],
                        ['text' => 'PATCH', 'is_correct' => 0],
                        ['text' => 'CONNECT', 'is_correct' => 0]
                    ],
                    'explanation' => 'PUT is idempotent: executing it multiple times produces the exact same end state as executing it once.'
                ],
                [
                    'topic' => 'Asynchronous JS (Promises, Async/Await)',
                    'difficulty' => 'medium',
                    'type' => 'mcq',
                    'marks' => 2.0,
                    'text' => 'What does <code>Promise.all([p1, p2, p3])</code> do if any one of the promises rejects?',
                    'options' => [
                        ['text' => 'Immediately rejects with the reason of the first rejected promise', 'is_correct' => 1],
                        ['text' => 'Waits for all others to resolve and ignores the failed promise', 'is_correct' => 0],
                        ['text' => 'Returns undefined for the failed promise', 'is_correct' => 0],
                        ['text' => 'Retries the rejected promise automatically up to 3 times', 'is_correct' => 0]
                    ],
                    'explanation' => 'Promise.all rejects immediately upon the first rejected input promise (fail-fast behavior).'
                ]
            ]
        ],

        'Artificial Intelligence' => [
            'topics' => ['Search & Heuristics', 'Supervised Learning', 'Neural Networks & Deep Learning', 'Optimization & Gradient Descent', 'Model Evaluation'],
            'questions' => [
                [
                    'topic' => 'Search & Heuristics',
                    'difficulty' => 'medium',
                    'type' => 'mcq',
                    'marks' => 2.0,
                    'text' => 'An A* search algorithm is guaranteed to be optimal if the heuristic function h(n) satisfies which condition for tree search?',
                    'options' => [
                        ['text' => 'Admissibility (never overestimates the true cost to reach goal)', 'is_correct' => 1],
                        ['text' => 'Monotonicity with negative weights', 'is_correct' => 0],
                        ['text' => 'Overestimation by at least 10%', 'is_correct' => 0],
                        ['text' => 'Random Gaussian distribution', 'is_correct' => 0]
                    ],
                    'explanation' => 'Admissibility ensures A* never rules out a truly optimal path by overestimating remaining costs.'
                ],
                [
                    'topic' => 'Neural Networks & Deep Learning',
                    'difficulty' => 'medium',
                    'type' => 'mcq',
                    'marks' => 2.0,
                    'text' => 'Which activation function is defined as f(x) = max(0, x) and is widely used in deep neural network hidden layers to mitigate the vanishing gradient problem?',
                    'options' => [
                        ['text' => 'ReLU (Rectified Linear Unit)', 'is_correct' => 1],
                        ['text' => 'Sigmoid', 'is_correct' => 0],
                        ['text' => 'Tanh (Hyperbolic Tangent)', 'is_correct' => 0],
                        ['text' => 'Softmax', 'is_correct' => 0]
                    ],
                    'explanation' => 'ReLU outputs 0 for negative inputs and x for positive inputs, maintaining constant derivative 1 for positive values.'
                ]
            ]
        ]
    ];

    /**
     * Generates / suggests questions dynamically.
     * Matches predefined questions or generates dynamic synthetic questions based on keywords.
     */
    public static function suggestQuestions(
        string $subjectName,
        string $topicDetails = '',
        string $difficulty = 'mixed',
        string $questionType = 'all',
        int $count = 5
    ): array {
        $matched = [];

        // 1. Check knowledge base by subject key
        $canonicalSubject = self::findBestMatchingSubject($subjectName);

        if ($canonicalSubject && isset(self::$knowledgeBase[$canonicalSubject])) {
            $pool = self::$knowledgeBase[$canonicalSubject]['questions'];

            // Filter pool by topic keyword if provided
            if (!empty($topicDetails)) {
                $keywords = array_filter(array_map('trim', explode(',', strtolower($topicDetails))));
                $topicMatches = [];
                foreach ($pool as $q) {
                    $haystack = strtolower(($q['topic'] ?? '') . ' ' . ($q['text'] ?? '') . ' ' . ($q['explanation'] ?? ''));
                    foreach ($keywords as $kw) {
                        if (str_contains($haystack, $kw)) {
                            $topicMatches[] = $q;
                            break;
                        }
                    }
                }
                if (!empty($topicMatches)) {
                    $pool = array_merge($topicMatches, $pool);
                }
            }

            // Filter by difficulty
            if ($difficulty !== 'mixed') {
                $filtered = array_filter($pool, fn($q) => $q['difficulty'] === $difficulty);
                if (!empty($filtered)) $pool = array_values($filtered);
            }

            // Filter by type
            if ($questionType !== 'all') {
                $filtered = array_filter($pool, fn($q) => $q['type'] === $questionType);
                if (!empty($filtered)) $pool = array_values($filtered);
            }

            shuffle($pool);
            $matched = array_slice($pool, 0, $count);
        }

        // 2. If matched questions are fewer than requested, synthetically generate questions
        $needed = $count - count($matched);
        if ($needed > 0) {
            $synthetics = self::synthesizeQuestions($subjectName, $topicDetails, $difficulty, $questionType, $needed);
            $matched = array_merge($matched, $synthetics);
        }

        // Clean & format output
        $results = [];
        foreach ($matched as $idx => $q) {
            $results[] = [
                'id' => 'gen_' . ($idx + 1),
                'topic' => $q['topic'] ?? (!empty($topicDetails) ? $topicDetails : $subjectName),
                'difficulty' => $q['difficulty'] ?? ($difficulty === 'mixed' ? ['easy', 'medium', 'hard'][array_rand(['easy', 'medium', 'hard'])] : $difficulty),
                'question_type' => $q['type'] ?? 'mcq',
                'marks' => $q['marks'] ?? 1.0,
                'negative_marks' => 0.0,
                'question_text' => $q['text'] ?? $q['question_text'] ?? '',
                'options' => $q['options'] ?? [],
                'test_cases' => $q['test_cases'] ?? [],
                'explanation' => $q['explanation'] ?? 'Standard academic evaluation criterion.'
            ];
        }

        return array_slice($results, 0, $count);
    }

    /**
     * Synthetically generates intelligent questions for any custom topic / subject
     */
    private static function synthesizeQuestions(
        string $subjectName,
        string $topicDetails,
        string $difficulty,
        string $questionType,
        int $count
    ): array {
        $topic = !empty($topicDetails) ? trim($topicDetails) : trim($subjectName);
        $templates = [
            [
                'text' => "In the context of <strong>{$topic}</strong>, which of the following statements represents the primary architectural principle?",
                'type' => 'mcq',
                'options' => [
                    ['text' => "It encapsulates state and provides high cohesion with decoupled interfaces", 'is_correct' => 1],
                    ['text' => "It increases tight coupling between components to minimize execution latency", 'is_correct' => 0],
                    ['text' => "It requires manual linear memory reclamation across all subroutines", 'is_correct' => 0],
                    ['text' => "It bypasses validation layers to achieve synchronous I/O throughput", 'is_correct' => 0]
                ],
                'explanation' => "In {$topic}, decoupling and high cohesion form fundamental architectural best practices."
            ],
            [
                'text' => "Which of the following is considered the most significant trade-off when optimizing <strong>{$topic}</strong> for high throughput?",
                'type' => 'mcq',
                'options' => [
                    ['text' => "Increased memory utilization and computational overhead for caching", 'is_correct' => 1],
                    ['text' => "Total loss of fault tolerance and data persistence guarantees", 'is_correct' => 0],
                    ['text' => "Degradation of network packets across physical layer links", 'is_correct' => 0],
                    ['text' => "Deprecation of cryptographic security standards", 'is_correct' => 0]
                ],
                'explanation' => "High throughput optimization typically trades memory/cache footprint to reduce latency and disk/compute access."
            ],
            [
                'text' => "True or False: In <strong>{$topic}</strong>, idempotent operations guarantee identical system state even if invoked repeatedly.",
                'type' => 'true_false',
                'options' => [
                    ['text' => "True", 'is_correct' => 1],
                    ['text' => "False", 'is_correct' => 0]
                ],
                'explanation' => "Idempotency mathematically means f(f(x)) = f(x); multiple executions yield the exact same state without duplicate side-effects."
            ],
            [
                'text' => "Explain how <strong>{$topic}</strong> addresses concurrency control and prevents race conditions under heavy load.",
                'type' => 'short_answer',
                'options' => [],
                'explanation' => "Key mechanisms include mutual exclusion locks, atomic transactions, optimistic concurrency checks, and message queues."
            ],
            [
                'text' => "Which design pattern is most commonly adopted to implement scalable event-driven communication in <strong>{$topic}</strong>?",
                'type' => 'mcq',
                'options' => [
                    ['text' => "Publish-Subscribe (Observer) Pattern", 'is_correct' => 1],
                    ['text' => "Singleton Pattern", 'is_correct' => 0],
                    ['text' => "Flyweight Pattern", 'is_correct' => 0],
                    ['text' => "Decorator Pattern", 'is_correct' => 0]
                ],
                'explanation' => "The Publish-Subscribe pattern decouples event producers from consumers, enabling scalable asynchronous event broadcasting."
            ],
            [
                'text' => "Implement a clean function in Python to validate and sanitize input records for <strong>{$topic}</strong> operations.",
                'type' => 'coding',
                'options' => [],
                'test_cases' => [
                    ['input' => '"ValidData_123"', 'output' => 'True', 'is_hidden' => 0],
                    ['input' => '""', 'output' => 'False', 'is_hidden' => 0],
                    ['input' => '"<script>alert()</script>"', 'output' => 'False', 'is_hidden' => 1]
                ],
                'explanation' => "Ensure non-empty alphanumeric validation and check for unsafe injection characters."
            ]
        ];

        $generated = [];
        $diffs = ['easy', 'medium', 'hard'];

        for ($i = 0; $i < $count; $i++) {
            $t = $templates[$i % count($templates)];
            if ($questionType !== 'all' && $t['type'] !== $questionType) {
                // Adapt to requested type
                $t['type'] = $questionType;
                if ($questionType === 'true_false') {
                    $t['options'] = [
                        ['text' => 'True', 'is_correct' => 1],
                        ['text' => 'False', 'is_correct' => 0]
                    ];
                }
            }
            $diff = ($difficulty === 'mixed') ? $diffs[$i % 3] : $difficulty;
            $generated[] = [
                'topic' => $topic,
                'difficulty' => $diff,
                'type' => $t['type'],
                'marks' => ($diff === 'hard' ? 3.0 : ($diff === 'medium' ? 2.0 : 1.0)),
                'text' => $t['text'],
                'options' => $t['options'] ?? [],
                'test_cases' => $t['test_cases'] ?? [],
                'explanation' => $t['explanation']
            ];
        }

        return $generated;
    }

    private static function findBestMatchingSubject(string $name): ?string {
        $clean = strtolower(trim($name));
        foreach (array_keys(self::$knowledgeBase) as $sub) {
            $subClean = strtolower($sub);
            if (str_contains($clean, $subClean) || str_contains($subClean, $clean)) {
                return $sub;
            }
            // Match aliases like 'dsa', 'dbms', 'os', 'cn'
            if ($clean === 'dsa' && str_contains($subClean, 'data structures')) return $sub;
            if ($clean === 'dbms' && str_contains($subClean, 'database')) return $sub;
            if ($clean === 'os' && str_contains($subClean, 'operating')) return $sub;
            if ($clean === 'cn' && str_contains($subClean, 'network')) return $sub;
            if ($clean === 'ai' && str_contains($subClean, 'intelligence')) return $sub;
            if ($clean === 'web' && str_contains($subClean, 'web')) return $sub;
        }
        return null;
    }
}
