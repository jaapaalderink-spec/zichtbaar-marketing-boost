<?php
declare(strict_types=1);
function editable_fields(array $value, string $prefix = ''): array {
    $out = [];
    foreach ($value as $key => $item) {
        if ($prefix === '' && in_array($key, ['slug','num'], true)) continue;
        $path = $prefix === '' ? (string)$key : $prefix . '.' . $key;
        if (is_array($item)) $out += editable_fields($item, $path);
        else $out[$path] = $item;
    }
    return $out;
}
function field_label(string $path): string {
    $names = ['seoTitle'=>'Zoekresultaat: titel','seoDescription'=>'Zoekresultaat: beschrijving','badge'=>'Label boven de titel','titleBefore'=>'Titel: begin','titleAccent'=>'Titel: oranje accent','titleAfter'=>'Titel: einde','intro'=>'Introductie','cta'=>'Tekst op de contactknop','servicesTitle'=>'Kop boven de diensten','approachTitle'=>'Kop boven de werkwijze','approachIntro'=>'Introductie van de werkwijze','steps'=>'Werkwijze','benefits'=>'Wat we voor je doen','title'=>'Titel','text'=>'Toelichting','headline'=>'Hoofdtitel','label'=>'Korte aanduiding','closing'=>'Titel van het contactblok','closingText'=>'Toelichting bij het contactblok','contactTitle'=>'Titel van het contactblok','contactIntro'=>'Toelichting bij het contactblok'];
    return implode(' · ', array_map(fn($part) => ctype_digit($part) ? (string)((int)$part+1) : ($names[$part] ?? $part), explode('.', $path)));
}
function validated_fields(string $id, array $submitted): array {
    $content = defaults()[$id];
    foreach (editable_fields($content) as $path => $original) {
        $field = str_replace('.', '__', $path);
        $value = $submitted[$field] ?? null;
        if (!is_string($value) || !preg_match('//u', $value) || trim($value) === '' || strlen($value) > 5000) throw new InvalidArgumentException('Vul alle tekstvelden in (maximaal 5.000 bytes per veld).');
        $ref =& $content;
        foreach (explode('.', $path) as $part) $ref =& $ref[$part];
        $ref = trim($value); unset($ref);
    }
    return $content;
}
function save_page(string $id, int $version, array $content): void {
    db()->exec('BEGIN IMMEDIATE');
    try {
        if (page($id)['version'] !== $version) throw new RuntimeException('Deze pagina is ondertussen gewijzigd. Kopieer je tekst en laad de pagina opnieuw voordat je opslaat.');
        $stmt = db()->prepare('INSERT INTO pages(id,content,version,updated_at) VALUES(?,?,1,?) ON CONFLICT(id) DO UPDATE SET content=excluded.content, version=pages.version+1, updated_at=excluded.updated_at');
        $stmt->execute([$id, json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), time()]);
        db()->exec('COMMIT');
    } catch (Throwable $e) { db()->exec('ROLLBACK'); throw $e; }
}
