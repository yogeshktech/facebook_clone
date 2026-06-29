<?php

$src = __DIR__.'/../public/images/newbook-logo.jpg';
$img = @imagecreatefromjpeg($src);

if (! $img) {
    $src = __DIR__.'/../public/images/newbook-logo.png';
    $img = @imagecreatefrompng($src);
}

if (! $img) {
    fwrite(STDERR, "Could not load logo from {$src}\n");
    exit(1);
}

$w = imagesx($img);
$h = imagesy($img);
$iconsDir = __DIR__.'/../public/icons';

if (! is_dir($iconsDir)) {
    mkdir($iconsDir, 0777, true);
}

foreach ([192, 512] as $size) {
    $out = imagecreatetruecolor($size, $size);
    $white = imagecolorallocate($out, 255, 255, 255);
    imagefilledrectangle($out, 0, 0, $size, $size, $white);
    imagealphablending($out, true);

    $ratio = min($size * 0.88 / $w, $size * 0.88 / $h);
    $nw = (int) ($w * $ratio);
    $nh = (int) ($h * $ratio);
    $dx = (int) (($size - $nw) / 2);
    $dy = (int) (($size - $nh) / 2);

    imagecopyresampled($out, $img, $dx, $dy, 0, 0, $nw, $nh, $w, $h);
    imagepng($out, $iconsDir.'/icon-'.$size.'.png');
    imagedestroy($out);
}

foreach ([32, 180] as $size) {
    $out = imagecreatetruecolor($size, $size);
    $white = imagecolorallocate($out, 255, 255, 255);
    imagefilledrectangle($out, 0, 0, $size, $size, $white);

    $ratio = min($size * 0.9 / $w, $size * 0.9 / $h);
    $nw = (int) ($w * $ratio);
    $nh = (int) ($h * $ratio);
    $dx = (int) (($size - $nw) / 2);
    $dy = (int) (($size - $nh) / 2);

    imagecopyresampled($out, $img, $dx, $dy, 0, 0, $nw, $nh, $w, $h);

    $name = $size === 32 ? 'favicon.png' : 'apple-touch-icon.png';
    imagepng($out, __DIR__.'/../public/images/'.$name);
    imagedestroy($out);
}

imagedestroy($img);
echo "Logo icons generated.\n";
