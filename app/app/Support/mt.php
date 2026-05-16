<?php

if (! function_exists('mt')) {
    /**
     * Текст з БД для вітрини без машинного перекладу (лише trim).
     * Локалізація інтерфейсу — через __() та файли lang/*.
     *
     * @param  bool  $asHtml  Залишено для сумісності викликів; не використовується.
     */
    function mt(?string $text, bool $asHtml = false): string
    {
        return trim((string) ($text ?? ''));
    }
}
