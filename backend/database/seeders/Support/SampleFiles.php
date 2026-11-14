<?php

namespace Database\Seeders\Support;

use ZipArchive;

/**
 * Fabrique de petits fichiers RÉELS et valides (PDF, DOCX, XLSX) pour les données
 * de démonstration : ils se téléchargent et s'ouvrent normalement dans Word, Excel
 * ou un lecteur PDF. Si l'extension PHP « zip » est absente, DOCX/XLSX sont
 * remplacés par un PDF (voir resolveExtension()).
 */
class SampleFiles
{
    /** Extension réellement utilisable sur cette machine. */
    public static function resolveExtension(string $wanted): string
    {
        return in_array($wanted, ['docx', 'xlsx'], true) && ! class_exists(ZipArchive::class)
            ? 'pdf'
            : $wanted;
    }

    /**
     * Retourne le contenu binaire du fichier demandé.
     *
     * $targetBytes : taille approximative souhaitée. Le fichier est complété par du
     * remplissage invisible (commentaire PDF / XML) pour que les tailles affichées
     * dans l'application ressemblent à celles de vrais documents.
     */
    public static function make(string $extension, string $title, string $subtitle = '', int $targetBytes = 0): string
    {
        return match ($extension) {
            'docx' => self::docx($title, $subtitle, $targetBytes),
            'xlsx' => self::xlsx($title, $subtitle, $targetBytes),
            default => self::pdf($title, $subtitle, $targetBytes),
        };
    }

    /** Remplissage déterministe (lettres seulement : valable dans un commentaire PDF ou XML). */
    private static function filler(int $bytes, bool $lines): string
    {
        if ($bytes <= 0) {
            return '';
        }
        $block = str_repeat('archivium', 9); // 81 caractères
        $text = substr(str_repeat($block, intdiv($bytes, 81) + 1), 0, $bytes);

        return $lines ? chunk_split($text, 80, "\n") : $text;
    }

    /** PDF d'une page, écrit à la main (police standard Helvetica). */
    public static function pdf(string $title, string $subtitle = '', int $targetBytes = 0): string
    {
        $esc = fn (string $s) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], self::latin1($s));

        // Le remplissage est écrit en commentaires PDF (« % … »), ignorés par les lecteurs.
        $pad = self::filler(max(0, $targetBytes - 900), true);
        $pad = $pad === '' ? '' : '% '.str_replace("\n", "\n% ", rtrim($pad))."\n";

        $stream = $pad."BT /F1 20 Tf 60 760 Td ({$esc($title)}) Tj ET\n"
            ."BT /F1 11 Tf 60 735 Td ({$esc($subtitle)}) Tj ET\n"
            ."BT /F1 10 Tf 60 700 Td (Document de demonstration genere par Archivium.) Tj ET\n";

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}endstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$body}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }

    /** DOCX minimal (3 parties obligatoires du format Office Open XML). */
    public static function docx(string $title, string $subtitle = '', int $targetBytes = 0): string
    {
        $t = htmlspecialchars($title, ENT_XML1);
        $s = htmlspecialchars($subtitle, ENT_XML1);

        return self::zip([
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
                .'</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
                .'</Relationships>',
            'word/document.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
                .self::xmlPadding($targetBytes)
                ."<w:p><w:r><w:rPr><w:b/><w:sz w:val=\"36\"/></w:rPr><w:t>{$t}</w:t></w:r></w:p>"
                ."<w:p><w:r><w:t>{$s}</w:t></w:r></w:p>"
                .'<w:p><w:r><w:t>Document de démonstration généré par Archivium.</w:t></w:r></w:p>'
                .'</w:body></w:document>',
        ]);
    }

    /** XLSX minimal : une feuille avec trois lignes de texte (chaînes « inline »). */
    public static function xlsx(string $title, string $subtitle = '', int $targetBytes = 0): string
    {
        $cell = fn (string $ref, string $text) => '<c r="'.$ref.'" t="inlineStr"><is><t>'
            .htmlspecialchars($text, ENT_XML1).'</t></is></c>';

        return self::zip([
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                .'</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                .'</Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
                .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<sheets><sheet name="Feuille1" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                .'</Relationships>',
            'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
                .self::xmlPadding($targetBytes)
                .'<row r="1">'.$cell('A1', $title).'</row>'
                .'<row r="2">'.$cell('A2', $subtitle).'</row>'
                .'<row r="3">'.$cell('A3', 'Document de démonstration généré par Archivium.').'</row>'
                .'</sheetData></worksheet>',
        ]);
    }

    /** Commentaire XML de remplissage (la partie est stockée sans compression, voir zip()). */
    private static function xmlPadding(int $targetBytes): string
    {
        $pad = self::filler(max(0, $targetBytes - 1800), false);

        return $pad === '' ? '' : '<!-- '.$pad.' -->';
    }

    /** Assemble un zip en mémoire (via un fichier temporaire) et renvoie son contenu. */
    private static function zip(array $entries): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'arc');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
            // Sans compression : sinon le remplissage répétitif ne ferait presque aucun octet.
            $zip->setCompressionName($name, ZipArchive::CM_STORE);
        }
        $zip->close();

        $bytes = file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }

    /** Le PDF utilise WinAnsi : on convertit l'UTF-8 pour que les accents s'affichent. */
    private static function latin1(string $s): string
    {
        return mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
    }
}
