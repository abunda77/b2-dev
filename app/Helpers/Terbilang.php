<?php

namespace App\Helpers;

class Terbilang
{
    public static function make(float $number): string
    {
        $number = abs($number);
        $words = [
            '',
            'satu',
            'dua',
            'tiga',
            'empat',
            'lima',
            'enam',
            'tujuh',
            'delapan',
            'sembilan',
            'sepuluh',
            'sebelas',
        ];

        $temp = '';
        if ($number < 12) {
            $temp = ' '.$words[(int) $number];
        } elseif ($number < 20) {
            $temp = self::make($number - 10).' belas';
        } elseif ($number < 100) {
            $remainder = (int) ($number % 10);
            $temp = self::make((int) ($number / 10)).' puluh'.($remainder > 0 ? ' '.self::make($remainder) : '');
        } elseif ($number < 200) {
            $remainder = $number - 100;
            $temp = 'seratus'.($remainder > 0 ? ' '.self::make($remainder) : '');
        } elseif ($number < 1000) {
            $remainder = (int) ($number % 100);
            $temp = self::make((int) ($number / 100)).' ratus'.($remainder > 0 ? ' '.self::make($remainder) : '');
        } elseif ($number < 2000) {
            $remainder = $number - 1000;
            $temp = 'seribu'.($remainder > 0 ? ' '.self::make($remainder) : '');
        } elseif ($number < 1000000) {
            $remainder = (int) ($number % 1000);
            $temp = self::make((int) ($number / 1000)).' ribu'.($remainder > 0 ? ' '.self::make($remainder) : '');
        } elseif ($number < 1000000000) {
            $remainder = fmod($number, 1000000);
            $temp = self::make((int) ($number / 1000000)).' juta'.($remainder > 0 ? ' '.self::make($remainder) : '');
        } elseif ($number < 1000000000000) {
            $remainder = fmod($number, 1000000000);
            $temp = self::make((int) ($number / 1000000000)).' milyar'.($remainder > 0 ? ' '.self::make($remainder) : '');
        } elseif ($number < 1000000000000000) {
            $remainder = fmod($number, 1000000000000);
            $temp = self::make((int) ($number / 1000000000000)).' trilyun'.($remainder > 0 ? ' '.self::make($remainder) : '');
        }

        return trim($temp);
    }
}
