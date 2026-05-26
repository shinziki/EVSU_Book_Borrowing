<?php
/**
 * Minimal PDF generator with bordered table support.
 */
class SimplePdf
{
    private $pages = [];
    private $currentPage = '';
    private $y = 800;
    private $marginLeft = 40;
    private $marginRight = 40;
    private $pageWidth = 595;
    private $pageHeight = 842;
    private $tableWidth = 515;
    private $lineHeight = 14;
    private $fontSize = 10;
    private $minY = 50;
    private $imageObjects = [];

    public function addPage()
    {
        if ($this->currentPage !== '') {
            $this->pages[] = $this->currentPage;
        }
        $this->currentPage = '';
        $this->y = 800;
    }

    public function setFontSize($size)
    {
        $this->fontSize = (int) $size;
        $this->lineHeight = max(12, (int) round($size * 1.35));
    }

    public function writeTitle($text)
    {
        $this->setFontSize(16);
        $this->writeLine($text, true);
        $this->setFontSize(10);
        $this->y -= 4;
    }

    /**
     * Report cover: title lines on the left, logo on the right.
     */
    public function writeReportCoverHeader($line1, $line2, $logoPath = null, $subtitle = null)
    {
        $this->ensurePage();

        $logoSize = 70;
        $logoX = $this->pageWidth - $this->marginRight - $logoSize;
        $logoY = 728;

        if ($logoPath) {
            $this->drawImage($logoPath, $logoX, $logoY, $logoSize, $logoSize);
        }

        $this->y = 800;
        $this->setFontSize(16);
        $this->writeLine($line1, true);
        $this->writeLine($line2, true);
        $this->setFontSize(10);
        if ($subtitle !== null && $subtitle !== '') {
            $this->writeLine($subtitle);
        }
        $this->y -= 8;
    }

    /**
     * Draw an image on the current page (requires PHP GD extension).
     */
    public function drawImage($filePath, $x, $y, $displayWidth, $displayHeight)
    {
        $imageData = $this->loadImageAsJpeg($filePath);
        if (!$imageData) {
            return false;
        }

        $name = 'Im' . (count($this->imageObjects) + 1);
        $this->imageObjects[$name] = $imageData;

        $this->currentPage .= "q\n";
        $this->currentPage .= sprintf(
            "%.2F 0 0 %.2F %.2F %.2F cm\n",
            $displayWidth,
            $displayHeight,
            $x,
            $y
        );
        $this->currentPage .= "/{$name} Do\nQ\n";

        return true;
    }

    public function writeHeading($text)
    {
        $this->writeSectionHeading($text);
    }

    /**
     * Section title placed directly above its table (e.g. "1. Executive Summary").
     */
    public function writeSectionHeading($text)
    {
        $this->ensureSpace(18);
        $this->setFontSize(12);
        $this->writeLine($text, true);
        $this->setFontSize(10);
        // Minimal gap — table follows immediately below the heading.
    }

    /**
     * Spacing between sections (after a table, before the next numbered heading).
     */
    public function writeSectionBreak()
    {
        $this->y -= 24;
    }

    public function writeLine($text, $bold = false)
    {
        $this->ensurePage();
        $font = $bold ? '/F2' : '/F1';
        $wrapped = $this->wrapText((string) $text, 95);
        foreach ($wrapped as $line) {
            $this->ensureSpace($this->lineHeight);
            $escaped = $this->escapeText($line);
            $this->currentPage .= "BT {$font} {$this->fontSize} Tf {$this->marginLeft} {$this->y} Td ({$escaped}) Tj ET\n";
            $this->y -= $this->lineHeight;
        }
    }

    public function writeSpacer($lines = 1)
    {
        $this->y -= $this->lineHeight * $lines;
    }

    /**
     * Draw a 2-column key-value summary table.
     */
    public function drawKeyValueTable(array $pairs)
    {
        $colWidths = [220, 295];
        $rows = [];
        foreach ($pairs as $label => $value) {
            $rows[] = [(string) $label, (string) $value];
        }
        $this->drawTable(['Metric', 'Value'], $rows, $colWidths, true);
    }

    /**
     * Draw a bordered data table with header row.
     *
     * @param array $headers Column labels
     * @param array $rows    Array of row arrays
     * @param array $colWidths Column widths in points (scaled to page width)
     */
    public function drawTable(array $headers, array $rows, array $colWidths, $repeatHeader = true)
    {
        $widths = $this->normalizeWidths($colWidths);

        if (empty($rows)) {
            $rows = [['No records found']];
            $widths = [$this->tableWidth];
            $headers = ['Information'];
        }

        $this->drawTableRow($headers, $widths, true);

        foreach ($rows as $row) {
            if ($repeatHeader && $this->y < $this->minY + 40) {
                $this->addPage();
                $this->drawTableRow($headers, $widths, true);
            }
            $this->drawTableRow($row, $widths, false);
        }

    }

    public function output($filename)
    {
        if ($this->currentPage !== '') {
            $this->pages[] = $this->currentPage;
        }
        if (empty($this->pages)) {
            $this->addPage();
            $this->writeLine('No data available.');
            $this->pages[] = $this->currentPage;
        }

        $pdf = "%PDF-1.4\n";
        $objects = [];
        $objectCount = 0;

        $objects[++$objectCount] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[++$objectCount] = "<< /Type /Pages /Kids [] /Count 0 >>";
        $pagesObjIndex = $objectCount;

        $objects[++$objectCount] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $fontRegularId = $objectCount;
        $objects[++$objectCount] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";
        $fontBoldId = $objectCount;

        $imageObjectIds = [];
        foreach ($this->imageObjects as $name => $img) {
            $objects[++$objectCount] = "<< /Type /XObject /Subtype /Image /Width {$img['w']} /Height {$img['h']} "
                . "/ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode "
                . "/Length " . strlen($img['data']) . " >>\nstream\n{$img['data']}\nendstream";
            $imageObjectIds[$name] = $objectCount;
        }

        $xobjectResource = '';
        if (!empty($imageObjectIds)) {
            $pairs = [];
            foreach ($imageObjectIds as $name => $id) {
                $pairs[] = "/{$name} {$id} 0 R";
            }
            $xobjectResource = '/XObject << ' . implode(' ', $pairs) . ' >> ';
        }

        $pageIds = [];
        foreach ($this->pages as $pageContent) {
            $resources = "<< /Font << /F1 {$fontRegularId} 0 R /F2 {$fontBoldId} 0 R >> {$xobjectResource}>>";
            $objects[++$objectCount] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources {$resources} /Contents " . ($objectCount + 1) . " 0 R >>";
            $pageIds[] = $objectCount;
            $stream = "q\n" . $pageContent . "\nQ";
            $objects[++$objectCount] = "<< /Length " . strlen($stream) . " >>\nstream\n{$stream}\nendstream";
        }

        $kids = array_map(fn($id) => "{$id} 0 R", $pageIds);
        $objects[$pagesObjIndex] = "<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count " . count($pageIds) . " >>";

        $offsets = [0];
        for ($i = 1; $i <= $objectCount; $i++) {
            $offsets[$i] = strlen($pdf);
            $pdf .= "{$i} 0 obj\n{$objects[$i]}\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $pdf .= "xref\n0 " . ($objectCount + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $objectCount; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . ($objectCount + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        echo $pdf;
        exit;
    }

    private function drawTableRow(array $cells, array $widths, $isHeader)
    {
        $padX = 4;
        $padY = 4;
        $fontSize = $isHeader ? 9 : 8;
        $lineH = 11;

        while (count($cells) < count($widths)) {
            $cells[] = '';
        }
        $cells = array_slice($cells, 0, count($widths));

        $wrappedCells = [];
        $maxLines = 1;
        foreach ($cells as $i => $cell) {
            $maxChars = max(4, (int) floor(($widths[$i] - $padX * 2) / ($fontSize * 0.48)));
            $lines = $this->wrapText((string) $cell, $maxChars);
            $wrappedCells[$i] = $lines;
            $maxLines = max($maxLines, count($lines));
        }

        $rowHeight = $padY * 2 + $maxLines * $lineH;
        $this->ensureSpace($rowHeight + 4);

        $x = $this->marginLeft;
        $yTop = $this->y;
        $yBottom = $yTop - $rowHeight;

        if ($isHeader) {
            $this->currentPage .= "0.663 0.082 0.082 rg\n";
            $this->currentPage .= sprintf("%.2F %.2F %.2F %.2F re f\n", $x, $yBottom, $this->tableWidth, $rowHeight);
            $this->currentPage .= "0 0 0 RG\n";
        } else {
            $this->currentPage .= "1 1 1 rg\n";
            $this->currentPage .= sprintf("%.2F %.2F %.2F %.2F re f\n", $x, $yBottom, $this->tableWidth, $rowHeight);
            $this->currentPage .= "0 0 0 RG\n";
        }

        $this->currentPage .= "0.75 0.75 0.75 RG\n0.5 w\n";
        $cx = $x;
        foreach ($widths as $w) {
            $this->currentPage .= sprintf("%.2F %.2F m %.2F %.2F l S\n", $cx, $yBottom, $cx, $yTop);
            $cx += $w;
        }
        $this->currentPage .= sprintf("%.2F %.2F m %.2F %.2F l S\n", $cx, $yBottom, $cx, $yTop);
        $this->currentPage .= sprintf("%.2F %.2F m %.2F %.2F l S\n", $x, $yTop, $x + $this->tableWidth, $yTop);
        $this->currentPage .= sprintf("%.2F %.2F m %.2F %.2F l S\n", $x, $yBottom, $x + $this->tableWidth, $yBottom);
        $this->currentPage .= "0 0 0 RG\n";

        $font = $isHeader ? '/F2' : '/F1';
        $textColor = $isHeader ? '1 1 1 rg' : '0 0 0 rg';

        $cx = $x;
        foreach ($widths as $i => $w) {
            $lines = $wrappedCells[$i];
            $textY = $yTop - $padY - $fontSize;
            foreach ($lines as $line) {
                $escaped = $this->escapeText($line);
                $this->currentPage .= "{$textColor}\n";
                $this->currentPage .= "BT {$font} {$fontSize} Tf " . ($cx + $padX) . " {$textY} Td ({$escaped}) Tj ET\n";
                $textY -= $lineH;
            }
            $cx += $w;
        }

        $this->y = $yBottom;
    }

    private function normalizeWidths(array $colWidths)
    {
        $total = array_sum($colWidths);
        if ($total <= 0) {
            return array_fill(0, count($colWidths), $this->tableWidth / max(1, count($colWidths)));
        }
        $scale = $this->tableWidth / $total;
        return array_map(fn($w) => $w * $scale, $colWidths);
    }

    private function ensurePage()
    {
        if ($this->currentPage === '' && empty($this->pages)) {
            $this->addPage();
        }
    }

    private function ensureSpace($needed)
    {
        if ($this->y - $needed < $this->minY) {
            $this->addPage();
        }
    }

    private function wrapText($text, $maxChars)
    {
        $text = preg_replace('/\s+/', ' ', trim($text));
        if ($text === '') {
            return [''];
        }
        if (strlen($text) <= $maxChars) {
            return [$text];
        }
        $words = explode(' ', $text);
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $test = $current === '' ? $word : $current . ' ' . $word;
            if (strlen($test) <= $maxChars) {
                $current = $test;
            } else {
                if ($current !== '') {
                    $lines[] = $current;
                }
                $current = strlen($word) > $maxChars ? substr($word, 0, $maxChars) : $word;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }
        return $lines ?: [''];
    }

    /**
     * PDF standard fonts only support ASCII — strip/replace Unicode characters.
     */
    private function sanitizePdfText($text)
    {
        $text = (string) $text;
        $map = [
            '©' => '(c)',
            '®' => '(R)',
            '™' => '(TM)',
            '—' => '-',
            '–' => '-',
            '…' => '...',
            '“' => '"',
            '”' => '"',
            '‘' => "'",
            '’' => "'",
            '´' => "'",
            '•' => '-',
            '₱' => 'PHP ',
            '€' => 'EUR ',
            '£' => 'GBP ',
        ];
        $text = strtr($text, $map);

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($converted !== false) {
                $text = $converted;
            }
        }

        $text = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $text);

        return $text;
    }

    private function escapeText($text)
    {
        $text = $this->sanitizePdfText($text);
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function loadImageAsJpeg($path)
    {
        if (!is_file($path) || !function_exists('imagecreatefrompng')) {
            return null;
        }

        $info = @getimagesize($path);
        if (!$info) {
            return null;
        }

        $src = null;
        switch ($info[2]) {
            case IMAGETYPE_JPEG:
                $src = @imagecreatefromjpeg($path);
                break;
            case IMAGETYPE_PNG:
                $src = @imagecreatefrompng($path);
                break;
            case IMAGETYPE_GIF:
                $src = @imagecreatefromgif($path);
                break;
            default:
                return null;
        }

        if (!$src) {
            return null;
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $canvas = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagealphablending($canvas, true);
        imagecopy($canvas, $src, 0, 0, 0, 0, $w, $h);
        imagedestroy($src);

        ob_start();
        imagejpeg($canvas, null, 90);
        $data = ob_get_clean();
        imagedestroy($canvas);

        if ($data === false || $data === '') {
            return null;
        }

        return ['data' => $data, 'w' => $w, 'h' => $h];
    }
}
