<?php
class CertificateGenerator {
    public static function generateCertificateNumber(): string {
        $year = date('Y');
        $random = strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
        return "CERT-{$year}-{$random}";
    }

    public static function generateQRData(string $certNumber): string {
        return CERT_VERIFY_URL . '?cert=' . urlencode($certNumber);
    }

    public static function generate(array $data): array {
        $certNumber = self::generateCertificateNumber();
        $qrData = self::generateQRData($certNumber);
        $html = self::getCertificateHTML(array_merge($data, [
            'certificate_number' => $certNumber,
            'qr_data' => $qrData,
        ]));
        return [
            'certificate_number' => $certNumber,
            'qr_data' => $qrData,
            'html_content' => $html,
        ];
    }

    public static function getCertificateHTML(array $data): string {
        $studentName = htmlspecialchars($data['student_name'] ?? '');
        $examName    = htmlspecialchars($data['exam_name'] ?? '');
        $score       = htmlspecialchars($data['score'] ?? '');
        $totalMarks  = htmlspecialchars($data['total_marks'] ?? '');
        $percentage  = number_format((float)($data['percentage'] ?? 0), 2);
        $date        = htmlspecialchars($data['completion_date'] ?? date('F d, Y'));
        $certNumber  = htmlspecialchars($data['certificate_number'] ?? '');
        $institution = htmlspecialchars($data['institution_name'] ?? 'ExamPortal Online Academy');
        $qrData      = htmlspecialchars($data['qr_data'] ?? '');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Certificate of Achievement — {$studentName}</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
  @page { size: A4 landscape; margin: 0; }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: 'Plus Jakarta Sans', sans-serif;
    background: #0F172A;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    padding: 30px 20px;
    color: #1E293B;
  }
  .cert-action-bar {
    margin-bottom: 24px;
    display: flex;
    gap: 14px;
    z-index: 100;
  }
  .btn-cert-action {
    background: #4F46E5;
    color: #FFFFFF;
    border: none;
    padding: 10px 24px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.4);
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
    text-decoration: none;
  }
  .btn-cert-action:hover {
    background: #4338CA;
    transform: translateY(-2px);
  }
  .certificate-wrapper {
    width: 297mm;
    min-height: 210mm;
    background: #FFFFFF;
    position: relative;
    overflow: hidden;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.5);
    border-radius: 6px;
  }
  .cert-border-outer {
    position: absolute;
    inset: 12px;
    border: 3px solid #4F46E5;
    pointer-events: none;
  }
  .cert-border-inner {
    position: absolute;
    inset: 18px;
    border: 1px solid #C7D2FE;
    pointer-events: none;
  }
  .cert-content {
    position: relative;
    z-index: 10;
    padding: 45px 70px;
    text-align: center;
    min-height: 210mm;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
  }
  .cert-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
  }
  .cert-logo {
    font-size: 32px;
    font-weight: 800;
    color: #4F46E5;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .cert-institution {
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: #4F46E5;
  }
  .cert-title {
    font-family: 'Playfair Display', serif;
    font-size: 42px;
    font-weight: 700;
    color: #0F172A;
    margin-top: 10px;
  }
  .cert-subtitle {
    font-size: 13px;
    letter-spacing: 4px;
    text-transform: uppercase;
    color: #64748B;
    margin-top: 6px;
    font-weight: 600;
  }
  .cert-name {
    font-family: 'Playfair Display', serif;
    font-style: italic;
    font-size: 46px;
    color: #1E1B4B;
    margin: 12px 0;
    border-bottom: 2px solid #4F46E5;
    display: inline-block;
    padding-bottom: 4px;
  }
  .cert-body {
    font-size: 15px;
    color: #475569;
    max-width: 650px;
    margin: 0 auto;
    line-height: 1.5;
  }
  .cert-exam-name {
    font-size: 22px;
    font-weight: 800;
    color: #4F46E5;
    margin: 10px 0;
  }
  .cert-score-box {
    display: inline-flex;
    gap: 40px;
    background: #EEF2FF;
    border: 1.5px solid #C7D2FE;
    border-radius: 12px;
    padding: 10px 36px;
    margin: 10px auto;
  }
  .cert-score-item { text-align: center; }
  .cert-score-value { font-size: 24px; font-weight: 800; color: #4F46E5; }
  .cert-score-label { font-size: 10px; text-transform: uppercase; letter-spacing: 2px; color: #64748B; font-weight: 700; }
  .cert-signatures {
    display: flex;
    justify-content: space-around;
    align-items: flex-end;
    margin-top: 15px;
  }
  .cert-sig { text-align: center; }
  .cert-sig-line { width: 140px; height: 1.5px; background: #334155; margin: 0 auto 6px; }
  .cert-sig-name { font-size: 13px; font-weight: 700; color: #1E293B; }
  .cert-sig-title { font-size: 11px; color: #64748B; font-weight: 600; }
  .cert-seal {
    width: 64px; height: 64px;
    background: linear-gradient(135deg, #4F46E5, #7C3AED);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 28px;
    box-shadow: 0 6px 18px rgba(79, 70, 229, 0.4);
  }
  .cert-footer {
    margin-top: 20px;
    padding-top: 12px;
    border-top: 1px solid #E2E8F0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 11px;
    color: #94A3B8;
  }
  .cert-number { font-family: monospace; font-weight: 700; letter-spacing: 1px; color: #334155; }
  .cert-verify { color: #4F46E5; font-weight: 600; }

  @media print {
    body { background: #FFFFFF; padding: 0; }
    .cert-action-bar { display: none !important; }
    .certificate-wrapper { box-shadow: none !important; border-radius: 0; width: 100vw; height: 100vh; }
  }
</style>
</head>
<body>
  <div class="cert-action-bar">
    <button class="btn-cert-action" onclick="window.print()">
      <i class="bi bi-printer-fill"></i> Print / Save as PDF
    </button>
    <button class="btn-cert-action" style="background:#334155;" onclick="window.close()">
      <i class="bi bi-x-circle"></i> Close Window
    </button>
  </div>

  <div class="certificate-wrapper">
    <div class="cert-border-outer"></div>
    <div class="cert-border-inner"></div>
    <div class="cert-content">
      <div class="cert-header">
        <div class="cert-logo">🎓 ExamPortal</div>
        <div class="cert-institution">{$institution}</div>
      </div>

      <div>
        <div class="cert-title">Certificate of Achievement</div>
        <div class="cert-subtitle">This is officially awarded to</div>
      </div>

      <div class="cert-name">{$studentName}</div>

      <p class="cert-body">
        for demonstrating academic excellence and successfully completing the certified examination with distinction.
      </p>

      <div class="cert-exam-name">{$examName}</div>

      <div class="cert-score-box">
        <div class="cert-score-item">
          <div class="cert-score-value">{$score} / {$totalMarks}</div>
          <div class="cert-score-label">Score Earned</div>
        </div>
        <div class="cert-score-item">
          <div class="cert-score-value">{$percentage}%</div>
          <div class="cert-score-label">Final Percentage</div>
        </div>
        <div class="cert-score-item">
          <div class="cert-score-value">PASSED</div>
          <div class="cert-score-label">Status</div>
        </div>
      </div>

      <div class="cert-signatures">
        <div class="cert-sig">
          <div class="cert-sig-line"></div>
          <div class="cert-sig-name">Academic Controller</div>
          <div class="cert-sig-title">Examination Department</div>
        </div>
        <div class="cert-seal">✓</div>
        <div class="cert-sig">
          <div class="cert-sig-line"></div>
          <div class="cert-sig-name">Authorized Signatory</div>
          <div class="cert-sig-title">{$institution}</div>
        </div>
      </div>

      <div class="cert-footer">
        <span class="cert-number">Certificate ID: {$certNumber}</span>
        <span>Issued on: <strong>{$date}</strong></span>
        <span class="cert-verify">Verification URL: {$qrData}</span>
      </div>
    </div>
  </div>
</body>
</html>
HTML;
    }
}
