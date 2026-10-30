<?php

namespace Jibiat\Socle\Places;

use Illuminate\Support\Str;

/**
 * Liste commune des lieux de Côte d'Ivoire, partagée par Jibiat et Jibiat Marchand : Abidjan, ses communes et leurs
 * quartiers les plus courants, puis les principales villes. Chaque lieu a un code fixe (« abidjan-cocody-angre ») que
 * les deux applications s'échangent ; les variantes d'écriture (« yop », « 2 plateaux ») aident à reconnaître les
 * adresses saisies à la main. Un code ne change jamais : un lieu renommé garde son code, un lieu retiré reste connu.
 */
final class Places
{
    public const CITY = 'city';

    public const COMMUNE = 'commune';

    public const NEIGHBORHOOD = 'neighborhood';

    /** Communes d'Abidjan : [variantes, quartiers (nom ou nom => variantes)] */
    private const ABIDJAN = [
        'Abobo' => [['abobo'], ['Abobo Gare', 'Abobo Baoulé', 'PK 18', 'Avocatier', 'Anador', 'Sagbé', "N'Dotré", 'Belleville', 'Akéikoi', 'Samaké']],
        'Adjamé' => [['adjame'], ['Liberté', '220 Logements', 'Williamsville', 'Bracodi', 'Paillet', 'Dallas']],
        'Anyama' => [['anyama'], []],
        'Attécoubé' => [['attecoube'], ['Santé', 'Locodjro', 'Agban', 'Abobo-Doumé']],
        'Bingerville' => [['binger'], []],
        'Cocody' => [['cocody'], [
            'Angré', 'Riviera 2' => ['riviera ii', 'riv 2'], 'Riviera 3' => ['riviera iii', 'riv 3'], 'Riviera Palmeraie' => ['palmeraie'],
            'Riviera Golf' => ['golf'], 'Deux Plateaux' => ['2 plateaux', 'ii plateaux', '2plateaux'], 'Vallon', 'Blockhauss', 'Danga',
            'Saint-Jean', 'Faya', 'Bonoumin', "M'Pouto" => ['mpouto'], 'Akouédo', 'Ambassades', 'Cité des Arts', 'Mermoz', 'Attoban',
        ]],
        'Koumassi' => [['koumassi'], ['Remblais', 'Grand Campement', 'Prodomo', 'Zone industrielle de Koumassi']],
        'Marcory' => [['marcory'], ['Zone 4' => ['zone iv'], 'Biétry', 'Anoumabo', 'Marcory Résidentiel', 'Champroux']],
        'Plateau' => [['plateau'], ['Cité administrative', 'Indénié']],
        'Port-Bouët' => [['port bouet', 'portbouet'], ['Vridi', 'Gonzagueville', 'Aéroport', 'Jean Folly', 'Adjouffou', 'Derrière Wharf']],
        'Songon' => [['songon'], []],
        'Treichville' => [['treich', 'treichville'], ['Arras', 'Biafra', 'Avenue 16', 'Gare de Bassam']],
        'Yopougon' => [['yop', 'yopougon', 'yopou'], [
            'Siporex', 'Niangon', 'Selmer', 'Toits Rouges', 'Maroc', 'Andokoi', 'Sideci', 'Wassakara', 'Gesco', 'Ananeraie',
            'Koweït' => ['koweit'], 'Kouté', 'Banco', 'Port-Bouët 2' => ['port bouet 2', 'pb2'], 'Micao', 'Yao Séhi', 'Mamie Adjoua',
        ]],
    ];

    /** Villes de l'intérieur : variantes */
    private const CITIES = [
        'Grand-Bassam' => ['bassam'], 'Bouaké' => ['bouake'], 'Yamoussoukro' => ['yakro'], 'San-Pédro' => ['san pedro'],
        'Daloa' => [], 'Korhogo' => [], 'Man' => [], 'Gagnoa' => [], 'Abengourou' => [], 'Assinie' => [], 'Jacqueville' => [],
        'Dabou' => [], 'Divo' => [], 'Soubré' => [], 'Bondoukou' => [],
    ];

    /** @var array<string, array{code: string, type: string, name: string, parent: ?string, aliases: list<string>}>|null */
    private static ?array $all = null;

    /**
     * Tous les lieux, parents avant enfants : Abidjan, chaque commune suivie de ses quartiers, puis les villes.
     *
     * @return list<array{code: string, type: string, name: string, parent: ?string, aliases: list<string>}>
     */
    public static function all(): array
    {
        return array_values(self::index());
    }

    /**
     * @return array{code: string, type: string, name: string, parent: ?string, aliases: list<string>}|null
     */
    public static function find(?string $code): ?array
    {
        return $code === null ? null : (self::index()[$code] ?? null);
    }

    /**
     * Nom à afficher : « Angré, Cocody » pour un quartier, le nom seul sinon.
     */
    public static function label(string $code): ?string
    {
        $place = self::find($code);
        if ($place === null) {
            return null;
        }

        return $place['type'] === self::NEIGHBORHOOD ? $place['name'].', '.self::find($place['parent'])['name'] : $place['name'];
    }

    /**
     * Le lieu et ceux qui le contiennent, du plus précis au plus large (quartier, commune, ville).
     *
     * @return list<string>
     */
    public static function lineage(string $code): array
    {
        $codes = [];
        for ($place = self::find($code); $place !== null; $place = self::find($place['parent'])) {
            $codes[] = $place['code'];
        }

        return $codes;
    }

    /**
     * Lieux cités dans un texte libre (« yop siporex », « Cocody Angré »), du plus précis au moins précis : un
     * quartier l'emporte sur sa commune, qui n'est alors plus renvoyée.
     *
     * @return list<array{code: string, type: string, name: string, parent: ?string, aliases: list<string>}>
     */
    public static function inText(string $text): array
    {
        $haystack = ' '.self::normalize($text).' ';
        $found = [];
        foreach (self::index() as $place) {
            foreach (self::names($place) as $name) {
                if ($name !== '' && ($position = strpos($haystack, " {$name} ")) !== false) {
                    $found[$place['code']] = [$place, $position];
                    break;
                }
            }
        }

        $ancestors = [];
        foreach ($found as $code => [$place]) {
            foreach (array_slice(self::lineage($code), 1) as $ancestor) {
                $ancestors[$ancestor] = true;
            }
        }
        $found = array_filter($found, fn ($code) => ! isset($ancestors[$code]), ARRAY_FILTER_USE_KEY);
        $rank = [self::NEIGHBORHOOD => 2, self::COMMUNE => 1, self::CITY => 0];
        // Quartier avant commune ; entre deux quartiers, le dernier cité ; puis le nom le plus long
        uasort($found, fn ($a, $b) => [$rank[$b[0]['type']], $b[1], mb_strlen($b[0]['name'])] <=> [$rank[$a[0]['type']], $a[1], mb_strlen($a[0]['name'])]);

        return array_values(array_map(fn ($entry) => $entry[0], $found));
    }

    /**
     * Lieu dont le nom ou une variante est exactement ce texte (« YOP » : Yopougon), sans chercher dans une phrase.
     *
     * @return array{code: string, type: string, name: string, parent: ?string, aliases: list<string>}|null
     */
    public static function named(string $name): ?array
    {
        $needle = self::normalize($name);
        if ($needle === '') {
            return null;
        }
        foreach (self::index() as $place) {
            if (in_array($needle, self::names($place), true)) {
                return $place;
            }
        }

        return null;
    }

    /**
     * Minuscules, sans accents ni ponctuation : « Port-Bouët » devient « port bouet ».
     */
    public static function normalize(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($value))));
    }

    /**
     * @return list<string>
     */
    private static function names(array $place): array
    {
        return array_values(array_unique(array_map(self::normalize(...), [$place['name'], ...$place['aliases']])));
    }

    /**
     * @return array<string, array{code: string, type: string, name: string, parent: ?string, aliases: list<string>}>
     */
    private static function index(): array
    {
        if (self::$all !== null) {
            return self::$all;
        }

        $all = [];
        $add = function (?string $parent, string $type, string $name, array $aliases) use (&$all): string {
            $code = ($parent === null ? '' : $parent.'-').Str::slug($name);
            $all[$code] = ['code' => $code, 'type' => $type, 'name' => $name, 'parent' => $parent, 'aliases' => array_values($aliases)];

            return $code;
        };

        $abidjan = $add(null, self::CITY, 'Abidjan', ['abj']);
        foreach (self::ABIDJAN as $commune => [$aliases, $neighborhoods]) {
            $parent = $add($abidjan, self::COMMUNE, $commune, $aliases);
            foreach ($neighborhoods as $key => $value) {
                [$name, $alias] = is_string($key) ? [$key, $value] : [$value, []];
                $add($parent, self::NEIGHBORHOOD, $name, $alias);
            }
        }
        foreach (self::CITIES as $city => $aliases) {
            $add(null, self::CITY, $city, $aliases);
        }

        return self::$all = $all;
    }
}
