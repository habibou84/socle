<?php

namespace Jibiat\Socle\Tests;

use Jibiat\Socle\Places\Places;
use PHPUnit\Framework\TestCase;

class PlacesTest extends TestCase
{
    public function test_codes_are_stable_and_unique(): void
    {
        $codes = array_column(Places::all(), 'code');
        $this->assertSame(count($codes), count(array_unique($codes)));
        $this->assertContains('abidjan', $codes);
        $this->assertContains('abidjan-cocody', $codes);
        $this->assertContains('abidjan-cocody-angre', $codes);
        $this->assertContains('abidjan-port-bouet', $codes);
        $this->assertContains('bouake', $codes);
        $this->assertSame('Angré, Cocody', Places::label('abidjan-cocody-angre'));
        $this->assertSame(['abidjan-cocody-angre', 'abidjan-cocody', 'abidjan'], Places::lineage('abidjan-cocody-angre'));
    }

    public function test_parents_come_before_children(): void
    {
        $seen = [];
        foreach (Places::all() as $place) {
            $this->assertTrue($place['parent'] === null || isset($seen[$place['parent']]), $place['code']);
            $seen[$place['code']] = true;
        }
    }

    public function test_places_are_found_in_free_text(): void
    {
        $this->assertSame(['abidjan-yopougon-siporex'], array_column(Places::inText('YOP SIPOREX'), 'code'));
        $this->assertSame(['abidjan-cocody-angre'], array_column(Places::inText('Cocody Angré 8e tranche'), 'code'));
        $this->assertSame('abidjan-cocody-deux-plateaux', Places::inText('2 plateaux vallon')[1]['code'] ?? null);
        $this->assertSame('abidjan-cocody-vallon', Places::inText('2 plateaux vallon')[0]['code']);
        $this->assertSame([], Places::inText('Carrefour Kennedy'));
    }

    public function test_exact_name_or_variant(): void
    {
        $this->assertSame('abidjan-yopougon', Places::named('Yop')['code']);
        $this->assertSame('abidjan-cocody-riviera-3', Places::named('riv 3')['code']);
        $this->assertNull(Places::named('Yop Siporex'));
        $this->assertNull(Places::named(''));
    }
}
