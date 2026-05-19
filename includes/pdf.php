<?php
// Minimal pure-PHP PDF writer (text-only, Helvetica). No external deps.
// Produces a valid single- or multi-page PDF suitable for Telegram delivery.

class SimplePDF {
    private $pages = [];      // array of arrays of [type, ...args]
    private $current;
    private $fontSize = 12;
    private $lineHeight = 16;
    private $marginX = 50;
    private $marginYTop = 60;
    private $marginYBottom = 60;
    private $pageWidth = 612;   // US Letter pts
    private $pageHeight = 792;
    private $y;

    public function __construct() {
        $this->newPage();
    }

    public function newPage() {
        $this->current = [];
        $this->pages[] = &$this->current;
        $this->y = $this->pageHeight - $this->marginYTop;
        unset($this->current);
        $this->current = &$this->pages[count($this->pages) - 1];
    }

    private function ensureRoom($needed = null) {
        $needed = $needed ?: $this->lineHeight;
        if ($this->y - $needed < $this->marginYBottom) {
            $this->newPage();
        }
    }

    public function h1($text) {
        $this->fontSize = 20; $this->lineHeight = 26;
        $this->writeLine($text, true);
        $this->fontSize = 12; $this->lineHeight = 16;
        $this->y -= 6;
    }

    public function h2($text) {
        $this->fontSize = 14; $this->lineHeight = 20;
        $this->writeLine($text, true);
        $this->fontSize = 12; $this->lineHeight = 16;
    }

    public function p($text) {
        foreach ($this->wrap($text, 90) as $line) {
            $this->writeLine($line, false);
        }
        $this->y -= 4;
    }

    public function kv($label, $value) {
        $this->writeLine($label . ': ' . (is_array($value) ? implode(', ', $value) : (string)$value), false);
    }

    public function hr() {
        $this->y -= 8;
        $this->ensureRoom();
        $this->current[] = ['hr', $this->marginX, $this->y, $this->pageWidth - $this->marginX, $this->y];
        $this->y -= 8;
    }

    private function writeLine($text, $bold) {
        $this->ensureRoom();
        $this->current[] = ['text', $this->marginX, $this->y, $text, $this->fontSize, $bold];
        $this->y -= $this->lineHeight;
    }

    private function wrap($text, $maxCharsPerLine) {
        $out = [];
        foreach (preg_split("/\r\n|\n|\r/", $text) as $line) {
            $words = preg_split('/\s+/', $line);
            $cur = '';
            foreach ($words as $w) {
                if (strlen($cur) + strlen($w) + 1 > $maxCharsPerLine && $cur !== '') {
                    $out[] = $cur;
                    $cur = $w;
                } else {
                    $cur = $cur === '' ? $w : $cur . ' ' . $w;
                }
            }
            if ($cur !== '') $out[] = $cur;
            if (empty($words)) $out[] = '';
        }
        return $out;
    }

    private function escape($s) {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }

    public function output($filePath) {
        // Build objects
        $objects = [];
        $add = function ($content) use (&$objects) {
            $objects[] = $content;
            return count($objects); // 1-based id
        };

        // Font objects
        $fontRegular = $add("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>");
        $fontBold    = $add("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>");

        // Build content streams for each page
        $pageObjIds = [];
        $contentObjIds = [];
        foreach ($this->pages as $page) {
            $stream = '';
            foreach ($page as $cmd) {
                if ($cmd[0] === 'text') {
                    [, $x, $y, $text, $size, $bold] = $cmd;
                    $fontTag = $bold ? '/F2' : '/F1';
                    $stream .= "BT {$fontTag} {$size} Tf {$x} {$y} Td (" . $this->escape($text) . ") Tj ET\n";
                } elseif ($cmd[0] === 'hr') {
                    [, $x1, $y1, $x2, $y2] = $cmd;
                    $stream .= "0.85 0.85 0.85 RG 0.5 w {$x1} {$y1} m {$x2} {$y2} l S\n";
                }
            }
            $contentId = $add('<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream");
            $contentObjIds[] = $contentId;
        }

        // Create Pages object placeholder, page objects reference it
        $pagesPlaceholder = count($objects) + count($this->pages) + 1; // pages obj id reserved after page objs

        // Create page objects
        foreach ($this->pages as $i => $page) {
            $contentId = $contentObjIds[$i];
            $pageObj = "<< /Type /Page /Parent {$pagesPlaceholder} 0 R "
                     . "/MediaBox [0 0 {$this->pageWidth} {$this->pageHeight}] "
                     . "/Contents {$contentId} 0 R "
                     . "/Resources << /Font << /F1 {$fontRegular} 0 R /F2 {$fontBold} 0 R >> >> >>";
            $pageObjIds[] = $add($pageObj);
        }

        // Pages object
        $kidsStr = implode(' ', array_map(fn($id) => "{$id} 0 R", $pageObjIds));
        $pagesObjId = $add("<< /Type /Pages /Count " . count($pageObjIds) . " /Kids [{$kidsStr}] >>");

        // Catalog
        $catalogId = $add("<< /Type /Catalog /Pages {$pagesObjId} 0 R >>");

        // Assemble
        $out = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $i => $obj) {
            $offsets[] = strlen($out);
            $out .= ($i + 1) . " 0 obj\n" . $obj . "\nendobj\n";
        }
        $xrefStart = strlen($out);
        $count = count($objects) + 1;
        $out .= "xref\n0 {$count}\n";
        $out .= "0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) {
            $out .= str_pad((string)$offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }
        $out .= "trailer\n<< /Size {$count} /Root {$catalogId} 0 R >>\n";
        $out .= "startxref\n{$xrefStart}\n%%EOF";

        file_put_contents($filePath, $out);
        return $filePath;
    }
}

function build_lead_pdf($filePath, $answers, $user = null) {
    $pdf = new SimplePDF();
    $pdf->h1('DiaFitus — New Lead');
    $pdf->p('Submitted: ' . date('Y-m-d H:i:s'));
    $pdf->hr();

    if ($user) {
        $pdf->h2('Contact');
        if (!empty($user['firstName'])) $pdf->kv('Name',  $user['firstName']);
        if (!empty($user['email']))     $pdf->kv('Email', $user['email']);
        if (!empty($user['phone']))     $pdf->kv('Phone', $user['phone']);
        $pdf->hr();
    }

    $labels = [
        'diabetes_type'      => 'Diabetes type',
        'gender'             => 'Gender',
        'age'                => 'Age',
        'weight'             => 'Weight (kg)',
        'motivation'         => 'Motivation',
        'doctor_recommended' => 'Doctor recommended',
        'exercise_history'   => 'Exercise history',
        'side_effects'       => 'Side effects',
        'goals'              => 'Goals',
        'location'           => 'Training location',
        'days_per_week'      => 'Days per week',
        'minutes_per_day'    => 'Minutes per day',
        'email'              => 'Email',
    ];

    $pdf->h2('Questionnaire answers');
    foreach ($labels as $key => $label) {
        if (isset($answers[$key])) $pdf->kv($label, $answers[$key]);
    }

    return $pdf->output($filePath);
}
