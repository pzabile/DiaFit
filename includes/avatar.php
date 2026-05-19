<?php
// Deterministic SVG avatars generated server-side. No external service,
// no real people, no images shipped in the repo. Each "seed" (the name)
// produces the same friendly cartoon-style face every time.

function avatar_svg($seed, $size = 96) {
    $hash = md5(strtolower(trim((string)$seed)));
    $h = function ($from, $count) use ($hash) {
        return hexdec(substr($hash, $from, 2)) % $count;
    };

    $skins   = ['#f3c8a0', '#e8b48d', '#d6a07f', '#b87c5c', '#8d5a3a', '#6b4022'];
    $hairs   = ['#1f1b16', '#3b2a1a', '#6b4423', '#a26a3a', '#caa15c', '#e1c07b', '#9a9a9a', '#cccccc'];
    $bgs     = ['#d6f0e1', '#cde7ff', '#fde6c1', '#f3d9d9', '#e6dffb', '#d4f4e1'];
    $shirts  = ['#16a36a', '#0d7d4f', '#2563eb', '#d8493c', '#7c3aed', '#ea580c', '#0891b2'];
    $hairStyles = ['short', 'long', 'bun', 'bald', 'curly'];

    $skin  = $skins [$h(0, count($skins))];
    $hair  = $hairs [$h(2, count($hairs))];
    $bg    = $bgs   [$h(4, count($bgs))];
    $shirt = $shirts[$h(6, count($shirts))];
    $style = $hairStyles[$h(8, count($hairStyles))];
    $eyeY  = 42 + ($h(10, 3));
    $smile = 'M 38 64 Q 50 ' . (72 + $h(12, 4)) . ' 62 64';

    $hairSVG = '';
    if ($style === 'short') {
        $hairSVG = "<path d='M 25 38 Q 50 12 75 38 L 75 48 Q 50 30 25 48 Z' fill='{$hair}'/>";
    } elseif ($style === 'long') {
        $hairSVG = "<path d='M 22 40 Q 50 10 78 40 L 80 80 Q 70 60 50 60 Q 30 60 20 80 Z' fill='{$hair}'/>";
    } elseif ($style === 'bun') {
        $hairSVG = "<circle cx='50' cy='22' r='10' fill='{$hair}'/><path d='M 28 40 Q 50 22 72 40 L 72 50 Q 50 36 28 50 Z' fill='{$hair}'/>";
    } elseif ($style === 'curly') {
        $hairSVG = "<circle cx='32' cy='32' r='8' fill='{$hair}'/><circle cx='44' cy='26' r='9' fill='{$hair}'/><circle cx='56' cy='26' r='9' fill='{$hair}'/><circle cx='68' cy='32' r='8' fill='{$hair}'/>";
    } // bald = no hair

    $size = (int) $size;
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="' . $size . '" height="' . $size . '" aria-hidden="true">'
        . '<circle cx="50" cy="50" r="50" fill="' . $bg . '"/>'
        . '<path d="M 20 100 Q 20 75 50 75 Q 80 75 80 100 Z" fill="' . $shirt . '"/>'
        . '<circle cx="50" cy="50" r="22" fill="' . $skin . '"/>'
        . $hairSVG
        . '<circle cx="42" cy="' . $eyeY . '" r="2.2" fill="#1f2937"/>'
        . '<circle cx="58" cy="' . $eyeY . '" r="2.2" fill="#1f2937"/>'
        . '<path d="' . $smile . '" stroke="#1f2937" stroke-width="2" fill="none" stroke-linecap="round"/>'
        . '</svg>';
}
