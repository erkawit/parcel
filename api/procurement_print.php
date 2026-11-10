<?php
/**
 * Procurement Print - ระบบสร้างเอกสารราชการ Word (.docx) สำหรับการจัดซื้อจัดจ้างภาครัฐ
 * รองรับทั้ง:
 * 1) แม่แบบชุดเอกสารต้นฉบับของหน่วยงาน (doc=template)
 * 2) สร้างเอกสารราชการ Word (.docx) อัตโนมัติพร้อมตารางรายการพัสดุครบทุกแถว:
 *    - doc=full_dynamic   : ชุดเอกสารจัดซื้อจัดจ้างครบทุกขั้นตอน + ตารางรายการพัสดุ
 *    - doc=request_memo   : บันทึกข้อความ แจ้งซ่อม / ขออนุมัติจัดซื้อเบื้องต้น (ผู้ขอเบิก)
 *    - doc=report_request : บันทึกข้อความ รายงานขอซื้อ/ขอจ้าง (ข้อ 22) + แต่งตั้งผู้ตรวจรับ
 *    - doc=price_spec     : ตารางวงเงินงบประมาณและราคากลาง (แบบ ป.ป.ช.) + ตารางรายการพัสดุ
 *    - doc=consider       : บันทึกข้อความ รายงานผลการพิจารณาและขออนุมัติสั่งซื้อ/สั่งจ้าง (ข้อ 79)
 *    - doc=inspect        : ใบตรวจรับการจัดซื้อ/จัดจ้าง + รายงานผลการตรวจรับพัสดุ (ข้อ 175)
 *    - doc=payment        : บันทึกข้อความ รายงานผลการพิจารณาและขออนุมัติเบิกจ่าย (ข้อ 16)
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

if (!class_exists('ZipArchive')) {
    die('<div style="font-family:sans-serif; padding:20px; border:1px solid red; background:#ffebeb; color:red; border-radius:5px;">
        <strong>System Error:</strong> The PHP Zip extension is missing.<br>
        Please enable <code>extension=zip</code> in your <strong>php.ini</strong> configuration and restart the Apache server.
    </div>');
}
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;

$id = intval($_GET['id'] ?? 0);
$docType = $_GET['doc'] ?? 'template'; // template, full_dynamic, request_memo, report_request, price_spec, consider, inspect, payment

// Fetch Procurement Data with all related officers and positions
$stmt = $pdo->prepare("SELECT p.*, 
                       u_req.full_name as requester_name, u_req.position as requester_position, u_req.position_level as requester_level, u_req.department as requester_dept,
                       u_insp.full_name as inspector_name, u_insp.position as inspector_position, u_insp.position_level as inspector_level,
                       u_supp.full_name as supply_officer_name, u_supp.position as supply_officer_position, u_supp.position_level as supply_officer_level,
                       u_dir.full_name as director_name, u_dir.position as director_position, u_dir.position_level as director_level,
                       u_head.full_name as head_officer_name, u_head.position as head_officer_position, u_head.position_level as head_officer_level
                       FROM procurements p
                       LEFT JOIN users u_req ON p.requester_id = u_req.id
                       LEFT JOIN users u_insp ON p.inspector_id = u_insp.id
                       LEFT JOIN users u_supp ON p.supply_officer_id = u_supp.id
                       LEFT JOIN users u_dir ON p.director_id = u_dir.id
                       LEFT JOIN users u_head ON p.head_officer_id = u_head.id
                       WHERE p.id = ?");
$stmt->execute([$id]);
$data = $stmt->fetch();

if (!$data) {
    die("ไม่พบข้อมูลรายการจัดซื้อจัดจ้าง");
}

// Fetch Items
$stmt = $pdo->prepare("SELECT pi.*, a.asset_code 
                       FROM procurement_items pi
                       LEFT JOIN assets a ON pi.asset_id = a.id
                       WHERE pi.procurement_id = ?
                       ORDER BY pi.id ASC");
$stmt->execute([$id]);
$items = $stmt->fetchAll();

// Fetch Agency Name from system_settings
$courtName = 'ศาลจังหวัดอุดรธานี';
try {
    $settings = $pdo->query("SELECT agency_name, org_name FROM system_settings WHERE id = 1")->fetch();
    if (!empty($settings['agency_name'])) {
        $courtName = trim($settings['agency_name']);
    } elseif (!empty($settings['org_name'])) {
        $courtName = trim($settings['org_name']);
    }
} catch (Exception $e) {
}

// Helper to combine position + position_level cleanly
function formatFullPosition(?string $pos, ?string $level, string $default = ''): string
{
    $p = trim($pos ?? '');
    $l = trim($level ?? '');
    if ($p === '') {
        return $default;
    }
    if ($l !== '' && strpos($p, $l) === false) {
        return $p . $l;
    }
    return $p;
}

// Format people & default fallbacks matching court hierarchy
$requesterName = $data['requester_name'] ?: '..................................................';
$requesterPos = formatFullPosition($data['requester_position'] ?? '', $data['requester_level'] ?? '', 'เจ้าหน้าที่');
$requesterDept = $data['requester_dept'] ?: 'ส่วนเทคโนโลยีสารสนเทศ';

$supplyName = $data['supply_officer_name'] ?: 'นางณัฐรินันท์ กุบแก้ว';
$supplyPos = formatFullPosition($data['supply_officer_position'] ?? '', $data['supply_officer_level'] ?? '', 'นักวิชาการพัสดุปฏิบัติการ');

$headOfficerName = $data['head_officer_name'] ?: 'นางราตรี ศรีจันทร์';
$headOfficerPos = formatFullPosition($data['head_officer_position'] ?? '', $data['head_officer_level'] ?? '', 'นักวิชาการเงินและบัญชีชำนาญการพิเศษ');

$inspectorName = $data['inspector_name'] ?: $requesterName;
$inspectorPos = formatFullPosition($data['inspector_position'] ?? '', $data['inspector_level'] ?? '', $requesterPos);

$directorName = $data['director_name'] ?: 'นางสาวเนตรดาว เฝ้าทรัพย์';
$directorPos = 'ผู้อำนวยการสำนักอำนวยการประจำ' . $courtName;

$headDirectorName = !empty($data['head_director_name']) ? $data['head_director_name'] : 'นายเลอภพอักขรา พรชัย';
$headDirectorPos = !empty($data['head_director_position']) ? $data['head_director_position'] : ('ผู้พิพากษาหัวหน้า' . $courtName);

// Format dates
$docDateStr = !empty($data['doc_date']) ? formatThaiDate($data['doc_date']) : formatThaiDate(date('Y-m-d'));
$deliveryDateStr = !empty($data['delivery_date']) ? formatThaiDate($data['delivery_date']) : $docDateStr;
$inspectDateStr = !empty($data['inspect_date']) ? formatThaiDate($data['inspect_date']) : $deliveryDateStr;
$fiscalYear = $data['fiscal_year'] ?: (date('Y') + 543);

// Format amounts
$totalAmount = floatval($data['total_amount'] ?? 0);
$totalPriceNum = (floor($totalAmount) == $totalAmount) ? number_format($totalAmount, 0) : number_format($totalAmount, 2);
$totalPriceNumDec = number_format($totalAmount, 2);
$totalPriceThai = bahtText($totalAmount);

// Format vendor & receipt
$vendorName = !empty($data['vendor_name']) ? trim($data['vendor_name']) : 'ผู้ประกอบการที่มีอาชีพขาย/รับจ้างโดยตรง';
$vendorAddress = !empty($data['vendor_address']) ? trim($data['vendor_address']) : '';
$vendorFull = $vendorName . ($vendorAddress !== '' ? " ตั้งอยู่เลขที่ {$vendorAddress}" : '');
$receiptNo = !empty($data['receipt_no']) ? trim($data['receipt_no']) : '........................';

// Format reason & subject
$subject = trim($data['subject'] ?: ($data['type'] === 'repair' ? 'ขออนุมัติจ้างซ่อมแซมครุภัณฑ์' : 'รายงานขออนุมัติจัดซื้อพัสดุ'));
$itemSummary = preg_replace('/^(รายงานขออนุมัติ|รายงานขอ|ขออนุมัติ)/u', '', $subject);
$itemSummary = trim($itemSummary) ?: $subject;

$reason = '';
if ($data['type'] === 'repair') {
    $reason = trim($data['repair_reason'] ?? '');
    if ($reason === '') {
        $reason = "ด้วย{$requesterDept} {$courtName} ได้แจ้งความชำรุดบกพร่องของครุภัณฑ์ ซึ่งจำเป็นต้องดำเนินการซ่อมแซมให้อยู่ในสภาพพร้อมใช้งาน เพื่อประโยชน์ในการปฏิบัติราชการและไม่เกิดความเสียหายต่อทางราชการ";
    }
} else {
    $reason = trim($data['buy_reason'] ?? '');
    if ($reason === '') {
        $reason = "ด้วยส่วนงานต่างๆ ใน{$courtName} มีความจำเป็นต้องใช้พัสดุดังกล่าวสำหรับใช้ในการปฏิบัติราชการ เพื่อให้การปฏิบัติราชการเป็นไปด้วยความสะดวกเรียบร้อยและไม่เกิดความเสียหายต่อทางราชการ";
    }
}

$budgetSource = !empty($data['budget_source'])
    ? trim($data['budget_source'])
    : "เงินงบประมาณประจำปีงบประมาณ พ.ศ. {$fiscalYear} ค่าใช้จ่ายด้านบริหารจัดการกิจกรรมสนับสนุนการพิจารณาพิพากษา";

// Format items summary strings
$itemLines = [];
$itemCount = count($items);
$totalQty = 0;
$firstUnit = $data['type'] === 'repair' ? 'งาน' : 'รายการ';

foreach ($items as $idx => $it) {
    $unit = !empty($it['unit']) ? $it['unit'] : ($data['type'] === 'repair' ? 'งาน' : 'ชิ้น');
    if ($idx === 0) {
        $firstUnit = $unit;
    }
    $qty = floatval($it['quantity'] ?? 1);
    $totalQty += $qty;
    $qtyStr = (floor($qty) == $qty) ? number_format($qty, 0) : number_format($qty, 2);
    $uPrice = number_format(floatval($it['unit_price'] ?? 0), 2);
    $tPrice = number_format(floatval($it['total_price'] ?? 0), 2);
    $codeStr = !empty($it['asset_code']) ? " (หมายเลขครุภัณฑ์ {$it['asset_code']})" : '';
    $specStr = !empty($it['spec_detail']) ? " {$it['spec_detail']}" : '';
    $itemLines[] = "{$it['item_name']}{$codeStr}{$specStr} จำนวน {$qtyStr} {$unit} (ราคาหน่วยละ {$uPrice} บาท รวม {$tPrice} บาท)";
}

$itemsListText = !empty($itemLines) ? implode(', ', $itemLines) : $itemSummary;
$unitSummary = ($itemCount === 1)
    ? ("จำนวน " . ((floor($totalQty) == $totalQty) ? number_format($totalQty, 0) : $totalQty) . " " . $firstUnit)
    : ("จำนวน {$itemCount} รายการ");

// ============================================================================
// MODE 1: Official Court Template Mode (doc=template)
// ============================================================================
if ($docType === 'template') {
    $templateMap = [
        'repair' => 'template_maintain.docx',
        'credit' => 'template_credit.docx',
        'cash' => 'template_cash.docx'
    ];

    $filename = $templateMap[$data['type']] ?? 'template_credit.docx';
    $templatePath = __DIR__ . '/../templates/' . $filename;

    if (file_exists($templatePath)) {
        try {
            $tp = new TemplateProcessor($templatePath);

            // Global & Document info
            $tp->setValue('court', $courtName);
            $tp->setValue('fiscal_year', $fiscalYear);
            $tp->setValue('doc_no', $data['doc_no']);
            $tp->setValue('doc_date', $docDateStr);
            $tp->setValue('delivery_date', $deliveryDateStr);
            $tp->setValue('inspect_date', $inspectDateStr);
            $tp->setValue('subject', $subject);

            // Content & Amounts
            $tp->setValue('item_summary', $itemSummary);
            $tp->setValue('items_list', $itemsListText);
            $tp->setValue('unit_summary', $unitSummary);
            $tp->setValue('item_count', $itemCount);
            $tp->setValue('reason', $reason);
            $tp->setValue('repair_reason', $reason);
            $tp->setValue('buy_reason', $reason);
            $tp->setValue('budget_source', $budgetSource);
            $tp->setValue('total_price_num', $totalPriceNum);
            $tp->setValue('total_price_thai', $totalPriceThai);
            $tp->setValue('vendor_name', $vendorName);
            $tp->setValue('vendor_full', $vendorFull);
            $tp->setValue('receipt_no', $receiptNo);

            // Cash-specific variables in template_cash.docx
            $tp->setValue('details_durables', "จัดซื้อ {$itemsListText} ตามรายละเอียดคุณลักษณะเฉพาะที่แนบท้าย");
            $tp->setValue('spending', "จาก{$budgetSource} จำนวนเงิน {$totalPriceNum} บาท ({$totalPriceThai})");
            $tp->setValue('details_responsible', "มอบหมายให้ {$requesterName} ตำแหน่ง {$requesterPos} เป็นผู้รับผิดชอบในการจัดซื้อและเบิกเงินทดรองราชการ");

            // Signatories
            $tp->setValue('requester_name', $requesterName);
            $tp->setValue('requester_position', $requesterPos);
            $tp->setValue('inspector_name', $inspectorName);
            $tp->setValue('inspector_position', $inspectorPos);
            $tp->setValue('supply_officer', $supplyName);
            $tp->setValue('position_supply_officer', $supplyPos);
            $tp->setValue('head_officer', $headOfficerName);
            $tp->setValue('position_head_officer', $headOfficerPos);
            $tp->setValue('director', $directorName);
            $tp->setValue('director_name', $directorName);
            $tp->setValue('head_director', $headDirectorName);

            $safeNo = str_replace(['/', '\\', ' '], '-', $data['doc_no']);
            $outName = "ชุดเอกสารจัดซื้อจัดจ้าง_{$safeNo}.docx";

            header("Content-Description: File Transfer");
            header('Content-Disposition: attachment; filename="' . rawurlencode($outName) . '"; filename*=UTF-8\'\'' . rawurlencode($outName));
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Transfer-Encoding: binary');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Expires: 0');

            $tp->saveAs('php://output');
            exit;
        } catch (Exception $e) {
            // Fall through to dynamic generator if template fails
        }
    }
    $docType = 'full_dynamic';
}

// ============================================================================
// MODE 2: Dynamic Official Thai Government Word (.docx) Generator (PhpWord)
// ============================================================================
$phpWord = new PhpWord();
$phpWord->setDefaultFontName('TH SarabunPSK');
$phpWord->setDefaultFontSize(16);

// Standard Thai Government Memo Page Setup (Top 2.5cm, Left 3.0cm, Right 2.0cm, Bottom 2.0cm)
$sectionStyle = [
    'paperSize' => 'A4',
    'marginTop' => 1134,    // ~2.0 cm
    'marginBottom' => 1134, // ~2.0 cm
    'marginLeft' => 1588,   // ~2.8 cm
    'marginRight' => 1134,  // ~2.0 cm
];

$fontBold = ['bold' => true, 'name' => 'TH SarabunPSK', 'size' => 16];
$fontNormal = ['bold' => false, 'name' => 'TH SarabunPSK', 'size' => 16];
$fontTitle = ['bold' => true, 'name' => 'TH SarabunPSK', 'size' => 24];
$fontSubTitle = ['bold' => true, 'name' => 'TH SarabunPSK', 'size' => 18];
$fontSmall = ['bold' => false, 'name' => 'TH SarabunPSK', 'size' => 15];

$paraTight = ['spaceAfter' => 0, 'spaceBefore' => 0, 'lineHeight' => 1.05];
$paraIndent = ['spaceAfter' => 60, 'spaceBefore' => 0, 'lineHeight' => 1.1, 'indentation' => ['firstLine' => 720]];
$paraCenter = ['alignment' => Jc::CENTER, 'spaceAfter' => 0, 'spaceBefore' => 0, 'lineHeight' => 1.05];
$paraRight = ['alignment' => Jc::RIGHT, 'spaceAfter' => 0, 'spaceBefore' => 0, 'lineHeight' => 1.05];

$garudaPath = __DIR__ . '/../img/garuda.png';

/**
 * Helper: Render Official Thai Government Memo Header (บันทึกข้อความ + ตราครุฑ)
 */
function addMemoHeader($section, string $courtName, string $docNo, string $docDate, string $subject, string $toRecipient, string $garudaPath): void
{
    global $fontBold, $fontNormal, $fontTitle, $paraTight;

    // Header table: Left = Garuda logo, Center = "บันทึกข้อความ"
    $headerTable = $section->addTable(['width' => 100 * 50, 'unit' => 'pct', 'borderSize' => 0, 'borderColor' => 'FFFFFF']);
    $headerTable->addRow();
    $cellLogo = $headerTable->addCell(3000, ['valign' => 'bottom']);
    if (file_exists($garudaPath)) {
        $cellLogo->addImage($garudaPath, ['width' => 42, 'height' => 45, 'alignment' => Jc::LEFT]);
    } else {
        $cellLogo->addText('', $fontNormal, $paraTight);
    }
    $cellTitle = $headerTable->addCell(6000, ['valign' => 'bottom']);
    $cellTitle->addText('บันทึกข้อความ', $fontTitle, ['alignment' => Jc::LEFT, 'spaceAfter' => 0]);

    // ส่วนราชการ
    $runGov = $section->addTextRun($paraTight);
    $runGov->addText('ส่วนราชการ   ', $fontBold);
    $runGov->addText($courtName, $fontNormal);

    // ที่ ... วันที่ ...
    $runNo = $section->addTextRun($paraTight);
    $runNo->addText('ที่   ', $fontBold);
    $runNo->addText($docNo . '                                             ', $fontNormal);
    $runNo->addText('วันที่   ', $fontBold);
    $runNo->addText($docDate, $fontNormal);

    // เรื่อง
    $runSubj = $section->addTextRun(['spaceAfter' => 80, 'spaceBefore' => 0, 'borderBottomSize' => 6, 'borderBottomColor' => '000000']);
    $runSubj->addText('เรื่อง   ', $fontBold);
    $runSubj->addText($subject, $fontNormal);

    // เรียน
    $runTo = $section->addTextRun(['spaceAfter' => 100, 'spaceBefore' => 60]);
    $runTo->addText('เรียน   ', $fontBold);
    $runTo->addText($toRecipient, $fontNormal);
}

/**
 * Helper: Render Items Table (ตารางรายการพัสดุ/ครุภัณฑ์)
 */
function addItemsTable($section, array $items, float $totalAmount, string $totalPriceThai): void
{
    global $fontBold, $fontNormal, $paraCenter, $paraTight, $paraRight;

    $tableStyle = [
        'borderSize' => 6,
        'borderColor' => '000000',
        'cellMarginTop' => 40,
        'cellMarginBottom' => 40,
        'cellMarginLeft' => 80,
        'cellMarginRight' => 80,
        'alignment' => JcTable::CENTER
    ];
    $table = $section->addTable($tableStyle);

    // Header Row
    $table->addRow(360);
    $table->addCell(700, ['bgColor' => 'F3F4F6'])->addText('ลำดับ', $fontBold, $paraCenter);
    $table->addCell(4300, ['bgColor' => 'F3F4F6'])->addText('รายการพัสดุ / รายละเอียดคุณลักษณะเฉพาะ', $fontBold, $paraCenter);
    $table->addCell(1000, ['bgColor' => 'F3F4F6'])->addText('จำนวน', $fontBold, $paraCenter);
    $table->addCell(1000, ['bgColor' => 'F3F4F6'])->addText('หน่วยนับ', $fontBold, $paraCenter);
    $table->addCell(1300, ['bgColor' => 'F3F4F6'])->addText('ราคา/หน่วย', $fontBold, $paraCenter);
    $table->addCell(1400, ['bgColor' => 'F3F4F6'])->addText('จำนวนเงิน (บาท)', $fontBold, $paraCenter);

    foreach ($items as $idx => $it) {
        $qty = floatval($it['quantity'] ?? 1);
        $qtyStr = (floor($qty) == $qty) ? number_format($qty, 0) : number_format($qty, 2);
        $unit = !empty($it['unit']) ? $it['unit'] : 'รายการ';
        $uPrice = number_format(floatval($it['unit_price'] ?? 0), 2);
        $tPrice = number_format(floatval($it['total_price'] ?? 0), 2);
        $nameDesc = $it['item_name'];
        if (!empty($it['asset_code'])) {
            $nameDesc .= " (รหัสครุภัณฑ์: {$it['asset_code']})";
        }
        if (!empty($it['spec_detail'])) {
            $nameDesc .= " - {$it['spec_detail']}";
        }

        $table->addRow();
        $table->addCell(700)->addText((string) ($idx + 1), $fontNormal, $paraCenter);
        $table->addCell(4300)->addText($nameDesc, $fontNormal, $paraTight);
        $table->addCell(1000)->addText($qtyStr, $fontNormal, $paraCenter);
        $table->addCell(1000)->addText($unit, $fontNormal, $paraCenter);
        $table->addCell(1300)->addText($uPrice, $fontNormal, $paraRight);
        $table->addCell(1400)->addText($tPrice, $fontNormal, $paraRight);
    }

    // Summary Row
    $table->addRow(360);
    $table->addCell(7000, ['gridSpan' => 4, 'bgColor' => 'F9FAFB'])->addText("ตัวอักษร: ({$totalPriceThai})", $fontBold, $paraCenter);
    $table->addCell(1300, ['bgColor' => 'F9FAFB'])->addText('รวมทั้งสิ้น', $fontBold, $paraRight);
    $table->addCell(1400, ['bgColor' => 'F9FAFB'])->addText(number_format($totalAmount, 2), $fontBold, $paraRight);
}

/**
 * Helper: Render 2-Column Executive Approval Block (เกษียนหนังสือราชการ 2 คอลัมน์)
 */
function addApprovalBlocks(
    $section,
    string $officerName,
    string $officerPos,
    string $headOfficerName,
    string $headOfficerPos,
    string $directorName,
    string $directorPos,
    string $headDirectorName,
    string $headDirectorPos,
    string $courtName,
    string $directorAction = 'อนุมัติตามเสนอ',
    string $headAction = 'เห็นชอบตามเสนอ'
): void {
    global $fontBold, $fontNormal, $paraCenter, $paraTight;

    // Officer Signature on the right
    $sigTable = $section->addTable(['width' => 100 * 50, 'unit' => 'pct', 'borderSize' => 0, 'borderColor' => 'FFFFFF']);
    $sigTable->addRow();
    $sigTable->addCell(4500)->addText('', $fontNormal, $paraTight);
    $cRight = $sigTable->addCell(5000);
    $cRight->addText('', $fontNormal, $paraTight);
    $cRight->addText('ลงชื่อ.......................................................', $fontNormal, $paraCenter);
    $cRight->addText("({$officerName})", $fontNormal, $paraCenter);
    $cRight->addText($officerPos, $fontNormal, $paraCenter);

    $section->addTextBreak(1);

    // 2-Column Table for Head Officer / Chief Judge (Left) and Director Approval (Right)
    $appTable = $section->addTable(['width' => 100 * 50, 'unit' => 'pct', 'borderSize' => 0, 'borderColor' => 'FFFFFF']);
    $appTable->addRow();

    $leftCell = $appTable->addCell(4800);
    $leftCell->addText("เรียน ผู้อำนวยการสำนักอำนวยการประจำ{$courtName}", $fontNormal, $paraTight);
    $leftCell->addText("       - เพื่อโปรดพิจารณาอนุมัติ", $fontNormal, $paraTight);
    $leftCell->addText('', $fontNormal, $paraTight);
    $leftCell->addText("({$headOfficerName})", $fontNormal, $paraCenter);
    $leftCell->addText($headOfficerPos, $fontNormal, $paraCenter);
    $leftCell->addText('', $fontNormal, $paraTight);
    $leftCell->addText("เรียน {$headDirectorPos}", $fontNormal, $paraTight);
    $leftCell->addText("       - เพื่อโปรดทราบและพิจารณาให้ความเห็นชอบ", $fontNormal, $paraTight);
    $leftCell->addText('', $fontNormal, $paraTight);
    $leftCell->addText("({$directorName})", $fontNormal, $paraCenter);
    $leftCell->addText($directorPos, $fontNormal, $paraCenter);

    $rightCell = $appTable->addCell(4800);
    $rightCell->addText($directorAction, $fontBold, $paraCenter);
    $rightCell->addText('', $fontNormal, $paraTight);
    $rightCell->addText("({$directorName})", $fontNormal, $paraCenter);
    $rightCell->addText($directorPos, $fontNormal, $paraCenter);
    $rightCell->addText('', $fontNormal, $paraTight);
    $rightCell->addText($headAction, $fontBold, $paraCenter);
    $rightCell->addText('', $fontNormal, $paraTight);
    $rightCell->addText("({$headDirectorName})", $fontNormal, $paraCenter);
    $rightCell->addText($headDirectorPos, $fontNormal, $paraCenter);
}

$actionWord = ($data['type'] === 'repair') ? 'จ้าง' : 'ซื้อ';

// ----------------------------------------------------------------------------
// DOC 0: บันทึกข้อความ แจ้งความชำรุด/ขออนุมัติซ่อม หรือ ขออนุมัติจัดซื้อเบื้องต้น (request_memo)
// ----------------------------------------------------------------------------
if ($docType === 'request_memo') {
    $sec = $phpWord->addSection($sectionStyle);
    addMemoHeader($sec, $courtName, $data['doc_no'], $docDateStr, $subject, "ผู้อำนวยการสำนักอำนวยการประจำ{$courtName}", $garudaPath);

    $sec->addText("ด้วยข้าพเจ้า {$requesterName} ตำแหน่ง {$requesterPos} ปฏิบัติหน้าที่ประจำ{$requesterDept} ขอรายงานเหตุผลและความจำเป็นในการ{$itemSummary} ดังนี้", $fontNormal, $paraIndent);
    $sec->addText("1. เหตุผลและความจำเป็น: {$reason}", $fontNormal, $paraIndent);
    $sec->addText("2. รายละเอียดพัสดุ/ครุภัณฑ์ที่ขอ{$actionWord} ({$unitSummary}) มีรายละเอียดดังต่อไปนี้", $fontNormal, $paraIndent);

    addItemsTable($sec, $items, $totalAmount, $totalPriceThai);

    $sec->addTextBreak(1);
    $sec->addText("จึงเรียนมาเพื่อโปรดพิจารณา หากเห็นชอบขอได้โปรดอนุมัติให้ส่วนคลัง (งานพัสดุ) ดำเนินการตามระเบียบต่อไป", $fontNormal, $paraIndent);

    addApprovalBlocks($sec, $requesterName, $requesterPos, $headOfficerName, $headOfficerPos, $directorName, $directorPos, $headDirectorName, $headDirectorPos, $courtName);
}

// ----------------------------------------------------------------------------
// DOC 1: บันทึกข้อความ รายงานขอซื้อ / รายงานขอจ้าง (ตามระเบียบฯ ข้อ 22)
// ----------------------------------------------------------------------------
if ($docType === 'report_request' || $docType === 'full_dynamic') {
    $sec = $phpWord->addSection($sectionStyle);
    addMemoHeader($sec, $courtName, $data['doc_no'], $docDateStr, $subject, "ผู้อำนวยการสำนักอำนวยการประจำ{$courtName}", $garudaPath);

    if ($data['type'] === 'cash') {
        $sec->addText("ด้วยสำนักอำนวยการประจำ{$courtName} มีความประสงค์จะ{$itemSummary} โดยวิธีเฉพาะเจาะจง ประจำปีงบประมาณ พ.ศ. {$fiscalYear} ซึ่งมีรายละเอียด ดังต่อไปนี้", $fontNormal, $paraIndent);
        $sec->addText("1. เหตุผลและความจำเป็น: {$reason}", $fontNormal, $paraIndent);
        $sec->addText("2. รายละเอียดคุณลักษณะของพัสดุหรือขอบเขตของงาน ({$unitSummary}):", $fontNormal, $paraIndent);
        addItemsTable($sec, $items, $totalAmount, $totalPriceThai);
        $sec->addText("3. วงเงินที่จะซื้อและราคากลาง: จาก{$budgetSource} จำนวนเงิน {$totalPriceNumDec} บาท ({$totalPriceThai}) โดยถือตามวงเงินที่จะซื้อเป็นราคากลางของพัสดุในครั้งนี้", $fontNormal, $paraIndent);
        $sec->addText("4. ผู้รับผิดชอบในการจัดซื้อ: มอบหมายให้ {$requesterName} ตำแหน่ง {$requesterPos} เป็นผู้รับผิดชอบในการจัดซื้อและเบิกจ่ายเงินทดรองราชการ", $fontNormal, $paraIndent);
        $sec->addText("จึงเรียนมาเพื่อโปรดพิจารณา หากเห็นชอบขอได้โปรด (1) เห็นชอบรายละเอียดคุณลักษณะเฉพาะของพัสดุ (2) อนุมัติให้ดำเนินการจัดซื้อ และ (3) อนุมัติให้จ่ายเงินทดรองราชการชำระให้แก่ผู้ขาย", $fontNormal, $paraIndent);
    } else {
        $sec->addText("ด้วยสำนักอำนวยการประจำ{$courtName} มีความประสงค์จะ{$itemSummary} โดยวิธีเฉพาะเจาะจง ซึ่งมีรายละเอียดตามระเบียบกระทรวงการคลังว่าด้วยการจัดซื้อจัดจ้างและการบริหารพัสดุภาครัฐ พ.ศ. 2560 ข้อ 22 ดังต่อไปนี้", $fontNormal, $paraIndent);
        $sec->addText("1. เหตุผลและความจำเป็นที่ต้องจัด{$actionWord}: {$reason}", $fontNormal, $paraIndent);
        $sec->addText("2. รายละเอียดของพัสดุที่จะ{$actionWord}: {$itemSummary} ({$unitSummary}) ตามตารางรายการดังนี้", $fontNormal, $paraIndent);
        addItemsTable($sec, $items, $totalAmount, $totalPriceThai);
        $sec->addText("3. ราคากลางและรายละเอียดของราคากลาง: ราคาที่สืบได้จากท้องตลาด เป็นเงิน {$totalPriceNumDec} บาท ({$totalPriceThai})", $fontNormal, $paraIndent);
        $sec->addText("4. วงเงินที่จะ{$actionWord}: {$budgetSource} จำนวน {$totalPriceNumDec} บาท ({$totalPriceThai}) รวมภาษีมูลค่าเพิ่มและค่าใช้จ่ายอื่นทั้งปวงแล้ว", $fontNormal, $paraIndent);
        $sec->addText("5. กำหนดเวลาที่ต้องการใช้พัสดุแล้วเสร็จ: ส่งมอบภายในวันที่ {$deliveryDateStr}", $fontNormal, $paraIndent);
        $sec->addText("6. วิธีที่จะ{$actionWord}และเหตุผลที่ต้อง{$actionWord}โดยวิธีนั้น: ดำเนินการโดยวิธีเฉพาะเจาะจง เนื่องจากเป็นการจัดซื้อจัดจ้างพัสดุที่มีการผลิต จำหน่าย ก่อสร้าง หรือให้บริการทั่วไป และมีวงเงินในการจัดซื้อจัดจ้างครั้งหนึ่งไม่เกิน 500,000 บาท ตามพระราชบัญญัติการจัดซื้อจัดจ้างและการบริหารพัสดุภาครัฐ พ.ศ. 2560 มาตรา 56 วรรคหนึ่ง (2) (ข) และกฎกระทรวงกำหนดวงเงินการจัดซื้อจัดจ้างพัสดุโดยวิธีเฉพาะเจาะจง พ.ศ. 2560", $fontNormal, $paraIndent);
        $sec->addText("7. หลักเกณฑ์การพิจารณาคัดเลือกข้อเสนอ: พิจารณาคัดเลือกข้อเสนอโดยใช้เกณฑ์ราคา", $fontNormal, $paraIndent);
        $sec->addText("8. การขออนุมัติแต่งตั้งผู้ตรวจรับพัสดุ: ขอแต่งตั้ง {$inspectorName} ตำแหน่ง {$inspectorPos} เป็นผู้ตรวจรับพัสดุ โดยให้มีอำนาจหน้าที่ทำการตรวจรับพัสดุให้เป็นไปตามเงื่อนไขของข้อตกลงและระเบียบกระทรวงการคลังฯ ข้อ 175", $fontNormal, $paraIndent);
        $sec->addText("จึงเรียนมาเพื่อโปรดทราบ หากเห็นชอบขอได้โปรด (1) อนุมัติให้ดำเนินการตามรายละเอียดในรายงานขอ{$actionWord}ข้างต้น และ (2) อนุมัติให้ใช้บันทึกข้อความฉบับนี้แทนคำสั่งแต่งตั้งผู้ตรวจรับพัสดุ", $fontNormal, $paraIndent);
    }

    addApprovalBlocks($sec, $supplyName, $supplyPos, $headOfficerName, $headOfficerPos, $directorName, $directorPos, $headDirectorName, $headDirectorPos, $courtName);
}

// ----------------------------------------------------------------------------
// DOC 2: ตารางวงเงินงบประมาณที่ได้รับจัดสรรและราคากลาง (แบบ ป.ป.ช.) + รายละเอียดคุณลักษณะเฉพาะ
// ----------------------------------------------------------------------------
if ($docType === 'price_spec' || $docType === 'full_dynamic') {
    $sec = $phpWord->addSection($sectionStyle);
    $sec->addText('ตารางแสดงวงเงินงบประมาณที่ได้รับจัดสรรและราคากลาง (ราคาอ้างอิง)', $fontSubTitle, $paraCenter);
    $sec->addText('ในการจัดซื้อจัดจ้างที่มิใช่งานก่อสร้าง', $fontSubTitle, ['alignment' => Jc::CENTER, 'spaceAfter' => 160]);

    $pTable = $sec->addTable([
        'borderSize' => 6,
        'borderColor' => '000000',
        'cellMarginTop' => 80,
        'cellMarginBottom' => 80,
        'cellMarginLeft' => 120,
        'cellMarginRight' => 120,
    ]);

    $pRows = [
        ['1. ชื่อโครงการ', "{$itemSummary} ({$unitSummary})"],
        ['   หน่วยงานเจ้าของโครงการ', $courtName],
        ['2. วงเงินงบประมาณที่ได้รับจัดสรร', "{$totalPriceNumDec} บาท ({$totalPriceThai})"],
        ['3. วันที่กำหนดราคากลาง (ราคาอ้างอิง)', "วันที่ {$docDateStr} เป็นเงิน {$totalPriceNumDec} บาท ({$totalPriceThai})"],
        ['4. แหล่งที่มาของราคากลาง (ราคาอ้างอิง)', "{$vendorName} (กำหนดตาม พ.ร.บ.การจัดซื้อจัดจ้างและการบริหารพัสดุภาครัฐ พ.ศ. 2560 มาตรา 4 ราคาที่สืบได้จากท้องตลาด)"],
        ['5. รายชื่อเจ้าหน้าที่ผู้กำหนดราคากลาง (ทุกคน)', "5.1 {$headOfficerName}  ตำแหน่ง {$headOfficerPos}\n5.2 {$supplyName}  ตำแหน่ง {$supplyPos}"]
    ];

    foreach ($pRows as $r) {
        $pTable->addRow();
        $pTable->addCell(3500, ['bgColor' => 'F9FAFB'])->addText($r[0], $fontBold, $paraTight);
        $cellVal = $pTable->addCell(6200);
        foreach (explode("\n", $r[1]) as $line) {
            $cellVal->addText($line, $fontNormal, $paraTight);
        }
    }

    $sec->addTextBreak(1);
    $sec->addText("รายละเอียดรายการพัสดุและราคากลางแนบท้ายรายงานขอ{$actionWord} ที่ {$data['doc_no']} ลงวันที่ {$docDateStr}", $fontBold, ['spaceAfter' => 80]);
    addItemsTable($sec, $items, $totalAmount, $totalPriceThai);

    $sec->addTextBreak(1);
    $sec->addText("ลงชื่อ....................................................... ผู้กำหนดราคากลาง/จัดทำขอบเขตงาน", $fontNormal, $paraRight);
    $sec->addText("({$supplyName})          ", $fontNormal, $paraRight);
    $sec->addText("{$supplyPos}          ", $fontNormal, $paraRight);
}

// ----------------------------------------------------------------------------
// DOC 3: บันทึกข้อความ รายงานผลการพิจารณาและขออนุมัติสั่งซื้อ/สั่งจ้าง (ข้อ 79)
// ----------------------------------------------------------------------------
if (($docType === 'consider' || $docType === 'full_dynamic') && $data['type'] !== 'cash') {
    $sec = $phpWord->addSection($sectionStyle);
    addMemoHeader($sec, $courtName, $data['doc_no'], $docDateStr, "รายงานผลการพิจารณาและขออนุมัติจัด{$actionWord}", "ผู้อำนวยการสำนักอำนวยการประจำ{$courtName}", $garudaPath);

    $sec->addText("ตามที่สำนักอำนวยการประจำ{$courtName} ได้อนุมัติให้ดำเนินการ{$itemSummary} ({$unitSummary}) โดยวิธีเฉพาะเจาะจง วงเงินงบประมาณจำนวน {$totalPriceNumDec} บาท ({$totalPriceThai}) รายละเอียดตามบันทึกรายงานขอ{$actionWord} ลงวันที่ {$docDateStr} นั้น", $fontNormal, $paraIndent);
    $sec->addText("ส่วนคลัง ได้ดำเนินการเจรจาตกลงราคากับผู้ประกอบการที่มีอาชีพ{$actionWord}โดยตรง ตามระเบียบกระทรวงการคลังว่าด้วยการจัดซื้อจัดจ้างและการบริหารพัสดุภาครัฐ พ.ศ. 2560 ข้อ 79 ปรากฏว่า {$vendorFull} ได้เสนอราคา{$itemSummary} ({$unitSummary}) เป็นเงินทั้งสิ้น {$totalPriceNumDec} บาท ({$totalPriceThai}) รวมภาษีมูลค่าเพิ่มและค่าใช้จ่ายอื่นทั้งปวงแล้ว ซึ่งไม่เกินวงเงินงบประมาณและราคากลางที่กำหนดไว้ ส่วนคลังพิจารณาแล้วเห็นสมควรจัด{$actionWord}จากผู้เสนอราคาดังกล่าว", $fontNormal, $paraIndent);

    addItemsTable($sec, $items, $totalAmount, $totalPriceThai);

    $sec->addTextBreak(1);
    $sec->addText("จึงเรียนมาเพื่อโปรดทราบ หากเห็นชอบขอได้โปรด", $fontNormal, $paraIndent);
    $sec->addText("1. อนุมัติให้จัด{$actionWord}จาก {$vendorName} ในวงเงิน {$totalPriceNumDec} บาท ({$totalPriceThai})", $fontNormal, $paraIndent);
    $sec->addText("2. อนุมัติให้ใช้บันทึกข้อความฉบับนี้เป็นหลักฐานในการจัด{$actionWord}ครั้งนี้ ตามกฎกระทรวง พ.ศ. 2560 ข้อ 4 (กรณีวงเงินไม่เกิน 100,000 บาท จะไม่ทำข้อตกลงเป็นหนังสือไว้ต่อกันก็ได้ แต่ต้องมีหลักฐานในการจัดซื้อจัดจ้างนั้น)", $fontNormal, $paraIndent);

    addApprovalBlocks($sec, $supplyName, $supplyPos, $headOfficerName, $headOfficerPos, $directorName, $directorPos, $headDirectorName, $headDirectorPos, $courtName);
}

// ----------------------------------------------------------------------------
// DOC 4: ใบตรวจรับการจัดซื้อ/จัดจ้าง + รายงานผลการตรวจรับพัสดุ (ข้อ 175)
// ----------------------------------------------------------------------------
if (($docType === 'inspect' || $docType === 'full_dynamic') && $data['type'] !== 'cash') {
    // 4.1 ใบตรวจรับการจัดซื้อ/จัดจ้าง
    $sec = $phpWord->addSection($sectionStyle);
    $sec->addText("ใบตรวจรับการจัด{$actionWord}", $fontTitle, $paraCenter);
    $sec->addText("เขียนที่ {$courtName}", $fontNormal, $paraRight);
    $sec->addText("วันที่ {$inspectDateStr}", $fontNormal, ['alignment' => Jc::RIGHT, 'spaceAfter' => 120]);

    $sec->addText("ตามบันทึกรายงานผลการพิจารณาและขออนุมัติสั่ง{$actionWord} ที่ {$data['doc_no']} ลงวันที่ {$docDateStr} สำนักอำนวยการประจำ{$courtName} ได้ตกลง{$itemSummary} ({$unitSummary}) กับ {$vendorName} โดยวิธีเฉพาะเจาะจง เป็นเงินทั้งสิ้น {$totalPriceNumDec} บาท ({$totalPriceThai}) รวมภาษีมูลค่าเพิ่มแล้ว นั้น", $fontNormal, $paraIndent);
    $sec->addText("ผู้ตรวจรับพัสดุ ได้ตรวจรับพัสดุในงาน{$actionWord}ดังกล่าว เมื่อวันที่ {$inspectDateStr} ตามใบส่งของ/ใบกำกับภาษี/ใบแจ้งหนี้ เลขที่ {$receiptNo} ลงวันที่ {$inspectDateStr} แล้ว ผลปรากฏดังนี้", $fontNormal, $paraIndent);

    addItemsTable($sec, $items, $totalAmount, $totalPriceThai);

    $sec->addTextBreak(1);
    $sec->addText("ผลการตรวจรับพัสดุ:", $fontBold, $paraTight);
    $sec->addText("[ / ] ถูกต้อง และครบถ้วนตามที่ตกลงกันไว้ทุกประการ", $fontNormal, $paraIndent);
    $sec->addText("[   ] ไม่ครบถ้วนตามที่ตกลงกันไว้", $fontNormal, $paraIndent);
    $sec->addText("ค่าปรับ:", $fontBold, $paraTight);
    $sec->addText("[ / ] ไม่มีค่าปรับ (ส่งมอบภายในกำหนด)", $fontNormal, $paraIndent);
    $sec->addText("[   ] มีค่าปรับ", $fontNormal, $paraIndent);

    $sec->addTextBreak(1);
    $sec->addText("จึงได้ลงลายมือชื่อไว้เป็นหลักฐาน", $fontNormal, $paraIndent);
    $sec->addTextBreak(1);
    $sec->addText("ลงชื่อ....................................................... ผู้ตรวจรับพัสดุ", $fontNormal, $paraCenter);
    $sec->addText("({$inspectorName})", $fontNormal, $paraCenter);
    $sec->addText($inspectorPos, $fontNormal, $paraCenter);

    // 4.2 บันทึกข้อความ รายงานผลการตรวจรับพัสดุ
    $sec2 = $phpWord->addSection($sectionStyle);
    addMemoHeader($sec2, $courtName, $data['doc_no'], $inspectDateStr, "รายงานผลการตรวจรับพัสดุ", "ผู้อำนวยการสำนักอำนวยการประจำ{$courtName}", $garudaPath);

    $sec2->addText("ตามที่สำนักอำนวยการประจำ{$courtName} ได้อนุมัติให้{$itemSummary} ({$unitSummary}) โดยวิธีเฉพาะเจาะจง ในวงเงิน {$totalPriceNumDec} บาท ({$totalPriceThai}) รายละเอียดตามรายงานขอ{$actionWord} และรายงานผลการพิจารณาและขออนุมัติสั่ง{$actionWord} ลงวันที่ {$docDateStr} นั้น", $fontNormal, $paraIndent);
    $sec2->addText("ผู้ตรวจรับพัสดุ ได้ทำการตรวจรับพัสดุในงาน{$actionWord}ดังกล่าวแล้ว ปรากฏว่า {$vendorName} ได้ส่งมอบพัสดุถูกต้อง ครบถ้วนตามที่ตกลงกันไว้ ตามต้นฉบับใบส่งของ/ใบกำกับภาษี/ใบแจ้งหนี้ เลขที่ {$receiptNo} ลงวันที่ {$inspectDateStr} เป็นเงินทั้งสิ้น {$totalPriceNumDec} บาท ({$totalPriceThai}) รวมภาษีมูลค่าเพิ่มแล้ว รายละเอียดปรากฏตามใบตรวจรับการจัด{$actionWord}ที่แนบมาพร้อมนี้", $fontNormal, $paraIndent);
    $sec2->addText("จึงเรียนมาเพื่อโปรดทราบ", $fontNormal, $paraIndent);

    addApprovalBlocks($sec2, $inspectorName, $inspectorPos, $headOfficerName, $headOfficerPos, $directorName, $directorPos, $headDirectorName, $headDirectorPos, $courtName, 'ทราบ / อนุมัติตามเสนอ', 'ทราบ');
}

// ----------------------------------------------------------------------------
// DOC 5: บันทึกข้อความ รายงานผลการพิจารณาและขออนุมัติเบิกจ่าย (ส่วนที่ 7 ข้อ 16 / เงินสด)
// ----------------------------------------------------------------------------
if ($docType === 'payment' || $docType === 'full_dynamic') {
    $sec = $phpWord->addSection($sectionStyle);
    $paySubj = ($data['type'] === 'cash')
        ? "รายงานผลการจัดซื้อและขออนุมัติเบิกจ่ายเงินคืนเงินทดรองราชการ"
        : "รายงานผลการพิจารณาและขออนุมัติเบิกจ่าย";

    addMemoHeader($sec, $courtName, $data['doc_no'], $inspectDateStr, $paySubj, "ผู้อำนวยการสำนักอำนวยการประจำ{$courtName}", $garudaPath);

    if ($data['type'] === 'cash') {
        $sec->addText("ตามที่ผู้อำนวยการสำนักอำนวยการประจำ{$courtName} ได้อนุมัติให้{$itemSummary} ({$unitSummary}) โดยวิธีเฉพาะเจาะจง ในวงเงิน {$totalPriceNumDec} บาท ({$totalPriceThai}) รวมภาษีมูลค่าเพิ่มแล้ว ตามรายงานขอซื้อลงวันที่ {$docDateStr} นั้น จึงขอรายงานผลการจัดซื้อดังนี้", $fontNormal, $paraIndent);
        $sec->addText("ผู้ได้รับมอบหมายได้ดำเนินการจัดซื้อพัสดุดังกล่าวกับ {$vendorName} เรียบร้อยแล้ว โดยเห็นว่าเป็นราคาที่เหมาะสม และได้จ่ายเงินทดรองราชการชำระให้แก่ผู้ขายเรียบร้อยแล้ว ปรากฏตามต้นฉบับใบเสร็จรับเงิน/ใบกำกับภาษี เลขที่ {$receiptNo} ลงวันที่ {$inspectDateStr} จำนวนเงิน {$totalPriceNumDec} บาท ({$totalPriceThai}) และได้รับมอบพัสดุไว้ถูกต้องครบถ้วนแล้ว จึงเห็นควรอนุมัติเบิกจ่ายเงินเพื่อคืนเงินทดรองราชการต่อไป", $fontNormal, $paraIndent);
        addItemsTable($sec, $items, $totalAmount, $totalPriceThai);
        $sec->addTextBreak(1);
        $sec->addText("จึงเรียนมาเพื่อโปรดพิจารณาอนุมัติเบิกจ่ายเงินคืนเงินทดรองราชการ", $fontNormal, $paraIndent);
        addApprovalBlocks($sec, $supplyName, $supplyPos, $headOfficerName, $headOfficerPos, $directorName, $directorPos, $headDirectorName, $headDirectorPos, $courtName, 'ทราบ / อนุมัติตามเสนอ', 'ทราบ / เห็นชอบตามเสนอ');
    } else {
        $sec->addText("ด้วยระเบียบกระทรวงการคลังว่าด้วยการจัดซื้อจัดจ้างและการบริหารพัสดุภาครัฐ พ.ศ. 2560 ส่วนที่ 7 ข้อ 16 กำหนดว่า เมื่อสิ้นสุดกระบวนการจัดซื้อจัดจ้างในแต่ละโครงการ ให้หน่วยงานของรัฐจัดให้มีการบันทึกรายงานผลการพิจารณา รายละเอียดวิธีการและขั้นตอนการจัดซื้อจัดจ้างพร้อมทั้งเอกสารหลักฐานประกอบ", $fontNormal, $paraIndent);
        $sec->addText("บัดนี้ ส่วนคลังได้ดำเนินการ{$itemSummary} ({$unitSummary}) โดยวิธีเฉพาะเจาะจงเสร็จสิ้นเรียบร้อยแล้ว จึงขอรายงานผลการพิจารณาดังนี้", $fontNormal, $paraIndent);
        $sec->addText("1. {$subject} เมื่อวันที่ {$docDateStr}", $fontNormal, $paraIndent);
        $sec->addText("2. รายงานผลการพิจารณาและขออนุมัติจัด{$actionWord} เมื่อวันที่ {$docDateStr}", $fontNormal, $paraIndent);
        $sec->addText("3. {$vendorName} ได้ส่งมอบพัสดุ/งานจ้าง ({$unitSummary}) ตามต้นฉบับใบส่งของ/ใบกำกับภาษี/ใบแจ้งหนี้ เลขที่ {$receiptNo} ลงวันที่ {$inspectDateStr} เป็นเงินทั้งสิ้น {$totalPriceNumDec} บาท ({$totalPriceThai}) รวมภาษีมูลค่าเพิ่มแล้ว และผู้ตรวจรับพัสดุได้ดำเนินการตรวจรับพัสดุพร้อมรายงานผลการตรวจรับเรียบร้อยแล้ว รายละเอียดตามเอกสารที่แนบมาพร้อมนี้", $fontNormal, $paraIndent);
        $sec->addText("จึงเรียนมาเพื่อโปรดทราบและอนุมัติเบิกจ่ายเงินให้แก่ผู้ขาย/ผู้รับจ้าง ต่อไป", $fontNormal, $paraIndent);
        addApprovalBlocks($sec, $headOfficerName, $headOfficerPos, $supplyName, $supplyPos, $directorName, $directorPos, $headDirectorName, $headDirectorPos, $courtName, 'อนุมัติเบิกจ่ายเงิน', 'เห็นชอบตามเสนอ');
    }
}

// Output the generated Word document
$docLabels = [
    'full_dynamic' => 'ชุดเอกสารครบชุดพร้อมตาราง',
    'request_memo' => '0_บันทึกแจ้งซ่อม-ขอซื้อเบื้องต้น',
    'report_request' => '1_รายงานขอซื้อขอจ้าง_ข้อ22',
    'price_spec' => '2_ตารางราคากลาง_ปปช',
    'consider' => '3_รายงานผลพิจารณา_ข้อ79',
    'inspect' => '4_ใบตรวจรับและรายงานผลตรวจรับ',
    'payment' => '5_ขออนุมัติเบิกจ่าย'
];
$prefix = $docLabels[$docType] ?? 'เอกสารจัดซื้อจัดจ้าง';
$safeNo = str_replace(['/', '\\', ' '], '-', $data['doc_no']);
$outFilename = "{$prefix}_{$safeNo}.docx";

header("Content-Description: File Transfer");
header('Content-Disposition: attachment; filename="' . rawurlencode($outFilename) . '"; filename*=UTF-8\'\'' . rawurlencode($outFilename));
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Transfer-Encoding: binary');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Expires: 0');

$objWriter = IOFactory::createWriter($phpWord, 'Word2007');
$objWriter->save('php://output');
exit;

/**
 * Helper: Generate Baht Text
 */
function bahtText($amount_number)
{
    $amount_number = number_format((float) $amount_number, 2, ".", "");
    $pt = strpos($amount_number, ".");
    $number = $fract = "";
    if ($pt === false) {
        $number = $amount_number;
    } else {
        $number = substr($amount_number, 0, $pt);
        $fract = substr($amount_number, $pt + 1);
    }

    $ret = "";
    $baht = ReadNumber($number);
    if ($baht != "") {
        $ret .= $baht . "บาท";
    } else {
        $ret .= "ศูนย์บาท";
    }

    $satang = ReadNumber($fract);
    if ($satang != "") {
        $ret .= $satang . "สตางค์";
    } else {
        $ret .= "ถ้วน";
    }

    return $ret;
}

function ReadNumber($number)
{
    $position_call = array("แสน", "หมื่น", "พัน", "ร้อย", "สิบ", "");
    $number_call = array("", "หนึ่ง", "สอง", "สาม", "สี่", "ห้า", "หก", "เจ็ด", "แปด", "เก้า");
    $number = (int) $number;
    $ret = "";
    if ($number == 0) {
        return $ret;
    }
    if ($number >= 1000000) {
        $ret .= ReadNumber(intval($number / 1000000)) . "ล้าน";
        $number = intval(fmod($number, 1000000));
    }

    $divider = 100000;
    $pos = 0;
    while ($number > 0) {
        $d = intval($number / $divider);
        $ret .= (($divider == 10) && ($d == 2)) ? "ยี่" :
            ((($divider == 10) && ($d == 1)) ? "" :
                ((($divider == 1) && ($d == 1) && ($ret != "")) ? "เอ็ด" : $number_call[$d]));
        $ret .= ($d ? $position_call[$pos] : "");
        $number = $number % $divider;
        $divider = intval($divider / 10);
        $pos++;
    }
    return $ret;
}
