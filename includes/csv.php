<?php
/** Excel-friendly CSV download (UTF-8 BOM). Guards against formula injection. */
function csv_safe($v)
{
    return (is_string($v) && $v !== '' && strpos("=+-@\t\r", $v[0]) !== false) ? "'" . $v : $v;
}
function send_csv(string $filename, array $headers, array $rows): void
{
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_.\-]/', '_', $filename) . '"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers, ',', '"', '\\');
    foreach ($rows as $r) fputcsv($out, array_map('csv_safe', $r), ',', '"', '\\');
    fclose($out);
    exit;
}
