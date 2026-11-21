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
        $this->assertCount(28, Contract::schemas());
        foreach (Contract::schemas() as $name) {
            $this->assertIsArray(Contract::schema($name), $name);
        }
    }

    public function test_a_cancellation_says_who_and_why(): void
    {
        $cancellation = Contract::example('order-cancelled')['data']['cancellation'];

        $this->assertSame('company', $cancellation['by']);
        $this->assertArrayHasKey($cancellation['reason'], Contract::CANCEL_REASONS_COMPANY);
        $this->assertSame(Contract::CANCEL_REASONS_COMPANY[$cancellation['reason']], $cancellation['reason_label']);
        $this->assertArrayHasKey('other', Contract::CANCEL_REASONS_MERCHANT);

        $wrong = Contract::example('order-cancelled');
        $wrong['data']['cancellation']['by'] = 'courier';
        $this->assertNotSame([], Contract::validate('order-response', $wrong));
    }

    public function test_a_return_says_why_the_parcel_goes_back(): void
    {
        $this->assertSame('refused', Contract::example('order-refused')['data']['return_cause']);
        $this->assertSame('merchant', Contract::example('order-return-requested')['data']['return_cause']);
        $this->assertSame(['refused', 'max_attempts', 'company', 'merchant'], array_keys(Contract::RETURN_CAUSES));

        $wrong = Contract::example('order-refused');
        $wrong['data']['return_cause'] = 'lost';
        $this->assertNotSame([], Contract::validate('order-response', $wrong));

        // Champ facultatif : une course d'une version plus ancienne de Jibiat reste valable
        $old = Contract::example('order-refused');
        unset($old['data']['return_cause']);
        $this->assertSame([], Contract::validate('order-response', $old));
    }

    public function test_a_reschedule_request_gives_the_missed_day_and_the_limit(): void
    {
        $reschedule = Contract::example('order-reschedule-requested')['data']['reschedule'];

        $this->assertSame('pending', $reschedule['status']);
        $this->assertSame(date('Y-m-d', strtotime('-1 day')), $reschedule['missed_day']);
        $this->assertSame(date('Y-m-d', strtotime('+'.Contract::RESCHEDULE_MAX_DAYS.' days')), $reschedule['max_date']);
        // Pas de demande : null ; absent pour une version plus ancienne
        $this->assertNull(Contract::example('order-cancelled')['data']['reschedule'] ?? null);

        $wrong = Contract::example('order-reschedule-requested');
        $wrong['data']['reschedule']['status'] = 'answered';
        $this->assertNotSame([], Contract::validate('order-response', $wrong));
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

    public function test_merchant_rating_tags_match_the_schema(): void
    {
        $ratings = Contract::schema('order')['properties']['ratings'];
        $this->assertSame(array_keys(Contract::MERCHANT_RATING_TAGS), $ratings['properties']['merchant']['properties']['tags']['items']['enum']);

        // Note inconnue refusée, course sans « ratings » (Jibiat plus ancien) toujours conforme
        $rated = Contract::example('order-delivered-rated');
        $rated['data']['ratings']['merchant']['score'] = 6;
        $this->assertNotSame([], Contract::validate('order-response', $rated));
        $old = Contract::example('order-delivered');
        unset($old['data']['ratings']);
        $this->assertSame([], Contract::validate('order-response', $old));
    }

    public function test_direct_connection_statuses_match_the_schemas(): void
    {
        $this->assertSame(Contract::CONNECT_DIRECT_STATUSES, Contract::schema('connect-direct')['properties']['data']['properties']['status']['enum']);
        $this->assertSame(Contract::CONNECT_DECISION_STATUSES, Contract::schema('connect-decision')['properties']['status']['enum']);
        $this->assertSame([], array_diff(Contract::CONNECT_DECISION_STATUSES, Contract::CONNECT_DIRECT_STATUSES));
        $this->assertSame(24, Contract::CONNECT_DIRECT_WAIT_HOURS);
    }

    public function test_a_direct_connection_gives_the_key_only_once_connected(): void
    {
        $this->assertNotNull(Contract::example('connect-direct-connected')['data']['connection']['api_key']);
        $this->assertNull(Contract::example('connect-direct-pending')['data']['connection']);
        $this->assertNull(Contract::example('connect-direct-confirmation')['data']['connection']);
        $this->assertNull(Contract::example('connect-decision-declined')['connection']);

        // Un téléphone non vérifié n'est pas une demande valable
        $request = Contract::example('connect-direct-request');
        unset($request['owner']['phone_verified_at']);
        $this->assertNotSame([], Contract::validate('connect-direct-request', $request));

        $unknown = Contract::example('connect-decision-connected');
        $unknown['status'] = 'pending';
        $this->assertNotSame([], Contract::validate('connect-decision', $unknown));
    }

    public function test_a_directory_without_direct_still_respects_the_contract(): void
    {
        $directory = Contract::example('directory');
        foreach ($directory['data'] as $i => $company) {
            unset($directory['data'][$i]['direct']);
        }
        $this->assertSame([], Contract::validate('directory', $directory));

        $quotes = Contract::example('directory-quotes')['data'];
        $this->assertTrue($quotes[0]['covered']);
        $this->assertIsInt($quotes[0]['total']);
        $this->assertNull($quotes[1]['total']);
    }

    public function test_a_return_handover_lists_its_parcels_and_a_known_status(): void
    {
        $this->assertSame(Contract::RETURN_HANDOVER_STATUSES, Contract::schema('return-handover')['properties']['status']['enum']);
        $this->assertSame([], array_diff(Contract::RETURN_HANDOVER_OPEN_STATUSES, Contract::RETURN_HANDOVER_STATUSES));
        $this->assertContains('return_handover.updated', Contract::WEBHOOK_EVENTS);
        $this->assertSame(Contract::WEBHOOK_EVENTS, Contract::schema('me')['properties']['data']['properties']['webhook_events']['items']['enum']);
        $this->assertNotContains('return_handover.updated', Contract::ORDER_EVENTS);

        $handover = Contract::example('webhook-return-handover')['data']['return_handover'];
        $this->assertSame('pending', $handover['status']);
        $this->assertCount(2, $handover['orders']);
        $this->assertSame('confirmed', Contract::example('return-handover-confirmed')['data']['status']);

        $unknown = Contract::example('return-handover-confirmed');
        $unknown['data']['status'] = 'lost';
        $this->assertNotSame([], Contract::validate('return-handover-response', $unknown));
    }
}
