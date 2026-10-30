<?php
declare(strict_types=1);

if (!defined('AB_ADMIN_DIR')) {
    http_response_code(404);
    exit;
}

function ab_field(string $key, string $label, string $type, int $max = 200, string $help = '', array $extra = []): array
{
    return ['key' => $key, 'label' => $label, 'type' => $type, 'max' => $max, 'help' => $help] + $extra;
}

function ab_schema(): array
{
    return ['sections' => [
        [
            'id' => 'teksten', 'title' => 'Teksten', 'kind' => 'fields',
            'intro' => 'Dit zijn de teksten onder de koppen op de website. De koppen zelf staan vast.',
            'fields' => [
                ab_field('hero_intro', 'Introductie bovenaan', 'textarea', 300, 'Staat onder de grote titel op de hoofdpagina.'),
                ab_field('hero_highlight', 'Uitgelichte zin (wit)', 'text', 160, 'Volgt direct op de introductie en valt extra op.'),
                ab_field('hero_afsluiting', 'Slotzin', 'text', 120, 'Optioneel. Staat achter de uitgelichte zin.', ['optional' => true]),
                ab_field('diensten_intro', 'Tekst bij Onze diensten', 'textarea', 300),
                ab_field('voordelen_intro', 'Tekst bij Voordelen', 'textarea', 300),
                ab_field('over_intro', 'Tekst bij Over ons', 'textarea', 600),
                ab_field('projecten_intro', 'Tekst bij Projecten', 'textarea', 300),
                ab_field('werkwijze_intro', 'Tekst bij Werkwijze', 'textarea', 300),
                ab_field('faq_intro', 'Tekst bij Veelgestelde vragen', 'textarea', 300),
                ab_field('contact_intro', 'Tekst boven het contactformulier', 'textarea', 300),
                ab_field('footer_omschrijving', 'Korte omschrijving onderaan de pagina', 'textarea', 300),
            ],
        ],
        [
            'id' => 'cijfers', 'title' => 'Cijfers', 'kind' => 'fields',
            'intro' => 'Deze cijfers staan op de hoofdpagina en bij Over ons. Vul alleen cijfers in die kloppen.',
            'fields' => [
                ab_field('jaren', 'Jaren ervaring', 'text', 12, 'Bijvoorbeeld 15+'),
                ab_field('projecten', 'Projecten opgeleverd', 'text', 12, 'Bijvoorbeeld 300+'),
                ab_field('disciplines', 'Disciplines in huis', 'text', 12, 'Bijvoorbeeld 9'),
                ab_field('reactietijd', 'Reactietijd', 'text', 12, 'Bijvoorbeeld 24u'),
                ab_field('aansturing', 'Eigen aansturing', 'text', 12, 'Bijvoorbeeld 100%'),
            ],
        ],
        [
            'id' => 'fotos', 'title' => "Vaste foto's", 'kind' => 'images',
            'intro' => 'Foto’s die op een vaste plek in de pagina staan.',
            'fields' => [
                ab_field('hero', 'Grote foto bovenaan', 'image'),
                ab_field('voordelen', 'Foto bij Voordelen', 'image'),
                ab_field('over', 'Foto bij Over ons', 'image'),
                ab_field('werkwijze', 'Foto bij Werkwijze', 'image'),
                ab_field('contact', 'Foto naast het contactformulier', 'image'),
            ],
        ],
        [
            'id' => 'diensten', 'title' => 'Diensten', 'kind' => 'list', 'itemTitle' => 'title',
            'addLabel' => 'Dienst toevoegen', 'min' => 3, 'max' => 20,
            'intro' => 'Diensten met “uitgelicht” krijgen een kaart met foto bovenaan de pagina. De andere staan als lijst onderaan.',
            'fields' => [
                ab_field('title', 'Naam van de dienst', 'text', 80),
                ab_field('short', 'Korte omschrijving', 'textarea', 200),
                ab_field('featured', 'Uitgelicht met foto op de hoofdpagina', 'bool'),
                ab_field('photo', 'Foto', 'image', 200, 'Verplicht bij uitgelichte diensten.', ['optional' => true]),
                ab_field('alt', 'Beschrijving van de foto', 'text', 160, 'Voor slechtzienden en Google. Leeg laten gebruikt de naam van de dienst.', ['optional' => true]),
            ],
        ],
        [
            'id' => 'voordelen', 'title' => 'Voordelen', 'kind' => 'list', 'itemTitle' => 'title',
            'addLabel' => 'Voordeel toevoegen', 'min' => 2, 'max' => 8,
            'fields' => [
                ab_field('title', 'Titel', 'text', 80),
                ab_field('text', 'Toelichting', 'textarea', 300),
            ],
        ],
        [
            'id' => 'werkwijze', 'title' => 'Werkwijze', 'kind' => 'list', 'itemTitle' => 'title',
            'addLabel' => 'Stap toevoegen', 'min' => 3, 'max' => 12,
            'fields' => [
                ab_field('title', 'Titel van de stap', 'text', 80),
                ab_field('text', 'Toelichting', 'textarea', 300),
            ],
        ],
        [
            'id' => 'faq', 'title' => 'Veelgestelde vragen', 'kind' => 'list', 'itemTitle' => 'q',
            'addLabel' => 'Vraag toevoegen', 'min' => 1, 'max' => 15,
            'fields' => [
                ab_field('q', 'Vraag', 'text', 200),
                ab_field('a', 'Antwoord', 'textarea', 1000),
            ],
        ],
        [
            'id' => 'projecten', 'title' => 'Projecten', 'kind' => 'list', 'itemTitle' => 'title',
            'addLabel' => 'Project toevoegen', 'min' => 1, 'max' => 24,
            'intro' => 'Elk project heeft een foto nodig. De categorie bepaalt de filterknoppen boven de projecten.',
            'fields' => [
                ab_field('title', 'Titel van het project', 'text', 100),
                ab_field('cat', 'Categorie', 'text', 40, 'Bijvoorbeeld Badkamer of Woningrenovatie.'),
                ab_field('blurb', 'Korte omschrijving', 'textarea', 300),
                ab_field('works', 'Uitgevoerde werkzaamheden', 'lines', 60, 'Eén werkzaamheid per regel (maximaal 12).'),
                ab_field('photo', 'Foto', 'image', 200),
                ab_field('alt', 'Beschrijving van de foto', 'text', 160, 'Voor slechtzienden en Google. Leeg laten gebruikt de titel.', ['optional' => true]),
            ],
        ],
    ]];
}

function ab_image_ok(string $path): bool
{
    if (preg_match('#^uploads/[a-f0-9]{16}\.(webp|jpg)$#', $path)) {
        return is_file(AB_ROOT . '/' . $path);
    }
    if (preg_match('#^assets/img/[A-Za-z0-9_-][A-Za-z0-9._-]*\.(jpe?g|png|webp)$#', $path) && strpos($path, '..') === false) {
        return is_file(AB_ROOT . '/' . $path);
    }
    return false;
}

function ab_validate_value(array $field, $value, string $where, array &$errors)
{
    $label = $field['label'];
    $optional = !empty($field['optional']);
    switch ($field['type']) {
        case 'text':
        case 'textarea':
            $v = clean_text($value, (int) $field['max'], $field['type'] === 'textarea');
            if ($v === '' && !$optional) {
                $errors[] = $where . ': “' . $label . '” is verplicht.';
            }
            if (is_string($value) && str_length(trim($value)) > (int) $field['max']) {
                $errors[] = $where . ': “' . $label . '” is te lang (maximaal ' . $field['max'] . ' tekens).';
            }
            return $v;
        case 'bool':
            return $value === true;
        case 'image':
            $v = is_string($value) ? trim($value) : '';
            if ($v === '') {
                if (!$optional) {
                    $errors[] = $where . ': kies een foto bij “' . $label . '”.';
                }
                return '';
            }
            if (!ab_image_ok($v)) {
                $errors[] = $where . ': de gekozen foto bij “' . $label . '” bestaat niet.';
                return '';
            }
            return $v;
        case 'lines':
            $lines = is_array($value) ? $value : [];
            $out = [];
            foreach ($lines as $line) {
                $line = clean_text($line, (int) $field['max']);
                if ($line !== '') {
                    $out[] = $line;
                }
            }
            if (count($out) > 12) {
                $errors[] = $where . ': “' . $label . '” mag maximaal 12 regels hebben.';
                $out = array_slice($out, 0, 12);
            }
            return $out;
    }
    return null;
}

function ab_validate_content($input): array
{
    $errors = [];
    $out = [];
    if (!is_array($input)) {
        return ['ok' => false, 'errors' => ['Ongeldige gegevens.'], 'content' => []];
    }
    foreach (ab_schema()['sections'] as $section) {
        $id = $section['id'];
        $title = $section['title'];
        $data = $input[$id] ?? null;
        if (!is_array($data)) {
            $errors[] = $title . ': gegevens ontbreken.';
            continue;
        }
        if ($section['kind'] === 'fields') {
            foreach ($section['fields'] as $f) {
                $out[$id][$f['key']] = ab_validate_value($f, $data[$f['key']] ?? '', $title, $errors);
            }
        } elseif ($section['kind'] === 'images') {
            foreach ($section['fields'] as $f) {
                $item = is_array($data[$f['key']] ?? null) ? $data[$f['key']] : [];
                $where = $title . ' (' . $f['label'] . ')';
                $src = ab_validate_value(ab_field('src', 'Foto', 'image'), $item['src'] ?? '', $where, $errors);
                $alt = ab_validate_value(ab_field('alt', 'Beschrijving van de foto', 'text', 160), $item['alt'] ?? '', $where, $errors);
                $out[$id][$f['key']] = ['src' => $src, 'alt' => $alt];
            }
        } else {
            $items = array_values($data);
            if (count($items) < $section['min'] || count($items) > $section['max']) {
                $errors[] = $title . ': aantal items moet tussen ' . $section['min'] . ' en ' . $section['max'] . ' liggen.';
                $items = array_slice($items, 0, (int) $section['max']);
            }
            $list = [];
            foreach ($items as $i => $item) {
                $item = is_array($item) ? $item : [];
                $row = [];
                $name = (string) ($item[$section['itemTitle']] ?? '');
                $where = $title . ', item ' . ($i + 1) . ($name !== '' ? ' (' . clean_text($name, 40) . ')' : '');
                foreach ($section['fields'] as $f) {
                    $row[$f['key']] = ab_validate_value($f, $item[$f['key']] ?? ($f['type'] === 'bool' ? false : ''), $where, $errors);
                }
                if ($id === 'diensten' && $row['featured'] && $row['photo'] === '') {
                    $errors[] = $where . ': een uitgelichte dienst heeft een foto nodig.';
                }
                $list[] = $row;
            }
            $out[$id] = $list;
        }
    }
    return ['ok' => !$errors, 'errors' => $errors, 'content' => $out];
}
