<?php

namespace Jibiat\Socle\Tests;

use Jibiat\Socle\Contract\Contract;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ContractTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function examples(): array
    {
        return array_combine(Contract::examples(), array_map(fn ($name) => [$name], Contract::examples()));
    }

    #[DataProvider('examples')]
    public function test_each_example_respects_its_schema(string $name): void
    {
        $this->assertContains(Contract::schemaOf($name), Contract::schemas());
        $this->assertSame([], Contract::validate(Contract::schemaOf($name), Contract::example($name)));
    }

    public function test_every_schema_is_valid_json(): void
    {
        $this->assertCount(16, Contract::schemas());
        foreach (Contract::schemas() as $name) {
            $this->assertIsArray(Contract::schema($name), $name);
        }
    }

    public function test_relative_dates_are_replaced(): void
    {
        $order = Contract::example('order-scheduled-tomorrow')['data'];

        $this->assertSame(date('Y-m-d', strtotime('+1 day')), $order['delivery']['scheduled_date']);
        $this->assertArrayNotHasKey('$schema', Contract::example('order-pending'));
        $this->assertStringNotContainsString('{{', json_encode(Contract::example('webhook-order-status-changed')));
    }

    public function test_a_missing_field_a_wrong_type_or_an_unknown_status_is_reported(): void
    {
        $order = Contract::example('order-pending');

        $missing = $order;
        unset($missing['data']['tracking_code']);
        $this->assertSame(['$.data.tracking_code : champ obligatoire absent'], Contract::validate('order-response', $missing));

        $wrongType = $order;
        $wrongType['data']['amounts']['cod_amount'] = '31500';
        $this->assertStringStartsWith('$.data.amounts.cod_amount : ', Contract::validate('order-response', $wrongType)[0] ?? '');

        $unknown = $order;
        $unknown['data']['status'] = 'perdue';
        $this->assertStringContainsString('« perdue » hors de la liste', Contract::validate('order-response', $unknown)[0] ?? '');

        $badDate = $order;
        $badDate['data']['delivery']['scheduled_date'] = '20/12/2026';
        $this->assertNotSame([], Contract::validate('order-response', $badDate));
    }

    public function test_an_added_field_never_breaks_the_contract(): void
    {
        $order = Contract::example('order-pending');
        $order['data']['nouveau_champ'] = ['x' => 1];

        $this->assertSame([], Contract::validate('order-response', $order));
    }

    public function test_statuses_and_events_match_the_schemas(): void
    {
        $order = Contract::schema('order');
        $this->assertSame(Contract::ORDER_STATUSES, $order['properties']['status']['enum']);
        $this->assertCount(17, Contract::ORDER_STATUSES);

        $webhook = Contract::schema('webhook');
        $this->assertSame(Contract::WEBHOOK_EVENTS, $webhook['properties']['event']['enum']);
        $this->assertSame([], array_diff(Contract::ORDER_EVENTS, Contract::WEBHOOK_EVENTS));
    }
}
