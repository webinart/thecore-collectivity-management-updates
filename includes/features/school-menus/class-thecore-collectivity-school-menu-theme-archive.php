<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-thecore-collectivity-school-menu-theme-validator.php';

final class TheCore_Collectivity_School_Menu_Theme_Archive {
    const IMAGE_TYPES = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'];
    const MAXIMUM_ENTRIES = 24;

    public static function read(string $filename) {
        if (!class_exists('ZipArchive')) return new WP_Error('menu_theme_archive', 'L’import ZIP est indisponible sur ce serveur.');
        if (!is_file($filename) || filesize($filename) > TheCore_Collectivity_School_Menu_Theme_Validator::MAXIMUM_BYTES) {
            return new WP_Error('menu_theme_archive', 'Choisissez un fichier ZIP de 8 Mio maximum.');
        }
        $zip = new ZipArchive();
        if ($zip->open($filename, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
            return new WP_Error('menu_theme_archive', 'Le fichier ZIP est illisible.');
        }

        try {
            $entries = self::entries($zip);
            if (!isset($entries['theme.json'])) {
                return new WP_Error('menu_theme_archive', 'Placez le fichier theme.json à la racine du ZIP.');
            }
            $theme = json_decode(self::contents($zip, $entries['theme.json']), true, 32, JSON_THROW_ON_ERROR);
            $theme = TheCore_Collectivity_School_Menu_Theme_Validator::validate_schema($theme);
            if (is_wp_error($theme)) return $theme;

            $photos = [];
            $embed = static function (string $source) use ($zip, $entries, &$photos): string {
                if (!str_starts_with($source, 'assets/')) return $source;
                if (!isset($entries[$source])) throw new InvalidArgumentException('Image manquante dans le ZIP : ' . $source . '.');
                if (!isset($photos[$source])) {
                    $binary = self::contents($zip, $entries[$source]);
                    $mime = self::IMAGE_TYPES[pathinfo($source, PATHINFO_EXTENSION)];
                    $image = @getimagesizefromstring($binary);
                    if (!$image || $image['mime'] !== $mime || $image[0] * $image[1] > 16000000) {
                        throw new InvalidArgumentException('Image illisible ou format incorrect : ' . $source . '.');
                    }
                    $photos[$source] = 'data:' . $mime . ';base64,' . base64_encode($binary);
                }
                return $photos[$source];
            };
            foreach ($theme['elements'] as $index => $element) {
                $theme['elements'][$index]['source'] = $embed($element['source']);
            }
            $theme['style']['backgroundImage'] = $embed($theme['style']['backgroundImage']);
            $theme['style']['badges']['logos'] = array_map($embed, $theme['style']['badges']['logos']);
            if (isset($theme['style']['animation'])) {
                foreach (['image', 'variant'] as $key) {
                    $theme['style']['animation'][$key] = $embed($theme['style']['animation'][$key]);
                }
            }
            return TheCore_Collectivity_School_Menu_Theme_Validator::validate($theme);
        } catch (JsonException $error) {
            return new WP_Error('menu_theme_archive', 'Le fichier theme.json ne contient pas de JSON valide.');
        } catch (InvalidArgumentException $error) {
            return new WP_Error('menu_theme_archive', $error->getMessage());
        } finally {
            $zip->close();
        }
    }

    private static function entries(ZipArchive $zip): array {
        if ($zip->numFiles > self::MAXIMUM_ENTRIES) {
            throw new InvalidArgumentException('Le ZIP contient trop de fichiers.');
        }
        $entries = [];
        $expandedBytes = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry = $zip->statIndex($index);
            $name = $entry['name'] ?? '';
            if (!$entry || isset($entries[$name]) || !empty($entry['encryption_method'])) {
                throw new InvalidArgumentException('Le ZIP contient une entrée illisible, protégée ou dupliquée.');
            }
            if ($name === 'assets/' && $entry['size'] === 0) {
                $entries[$name] = $entry;
                continue;
            }
            if ($name !== 'theme.json' && !preg_match('~^assets/[a-z0-9][a-z0-9._-]*\.(?:png|jpe?g|webp)$~D', $name)) {
                throw new InvalidArgumentException('Le ZIP doit contenir theme.json à la racine et des images PNG, JPEG ou WebP dans assets/.');
            }
            $limit = $name === 'theme.json' ? TheCore_Collectivity_School_Menu_Theme_Validator::MAXIMUM_BYTES : TheCore_Collectivity_School_Menu_Theme_Validator::MAXIMUM_IMAGE_BYTES;
            if ($entry['size'] < 1 || $entry['size'] > $limit) {
                throw new InvalidArgumentException('Un fichier du ZIP est vide ou dépasse la taille autorisée.');
            }
            $expandedBytes += $entry['size'];
            if ($expandedBytes > TheCore_Collectivity_School_Menu_Theme_Validator::MAXIMUM_BYTES) {
                throw new InvalidArgumentException('Le contenu du ZIP dépasse 8 Mio une fois décompressé.');
            }
            $entries[$name] = $entry;
        }
        return $entries;
    }

    private static function contents(ZipArchive $zip, array $entry): string {
        $content = $zip->getFromIndex($entry['index'], $entry['size'] + 1);
        if ($content === false || strlen($content) !== $entry['size']) {
            throw new InvalidArgumentException('Un fichier du ZIP est illisible.');
        }
        return $content;
    }
}
