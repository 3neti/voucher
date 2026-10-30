<?php

declare(strict_types=1);

namespace VoucherV2Experiment;

use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use Spatie\LaravelData\Data;

/** An explicit subscription; delivery and destination authorization are outside this DTO. */
final class WebhookSubscriptionData extends Data
{
    /** @param list<string> $events */
    public function __construct(public string $url, public array $events) {}
}

final class FeedbackV2Data extends Data
{
    /** @param list<WebhookSubscriptionData> $webhooks */
    public function __construct(public ?string $email = null, public ?string $mobile = null, public array $webhooks = []) {}
}

final class StampPresentationData extends Data
{
    public function __construct(public string $design_id, public int $design_version, public ?bool $show_logo = null, public ?bool $show_tagline = null, public ?string $claim_marker = null) {}
}

final class SharePresentationData extends Data
{
    public function __construct(public ?string $title = null, public ?string $description = null, public ?string $image_source = null) {}
}

final class PresentationData extends Data
{
    public function __construct(public ?StampPresentationData $stamp = null, public ?SharePresentationData $share = null) {}
}

/** Reference to an authoritative quote; not proof of price or payment. */
final class PricingDecisionData extends Data
{
    public function __construct(public string $quote_reference, public string $currency, public string $fee_bearer, public ?string $quote_hash = null) {}
}

final class CampaignIdentityData extends Data
{
    public function __construct(public string $campaign_reference, public string $campaign_revision, public string $template_id, public string $template_version, public string $definition_hash, public ?string $payment_recognition_reference = null) {}
}

/** An experimental v2 envelope retaining the actual v1 DTO as the core. */
final class VoucherInstructionV2 extends Data
{
    public function __construct(
        public VoucherInstructionsData $core,
        public ExecutionData $execution,
        public FeedbackV2Data $feedback,
        public ?PresentationData $presentation = null,
        public ?PricingDecisionData $pricing = null,
        public ?CampaignIdentityData $campaign = null,
    ) {}

    /** @return array<string,mixed> */
    public function instructionArray(): array
    {
        $core = json_decode(json_encode($this->core->toArray(), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        $core['execution'] = $this->execution->toArray();
        $core['feedback'] = $this->feedback->toArray();
        if ($this->presentation !== null) {
            $core['presentation'] = $this->presentation->toArray();
        }
        if ($this->pricing !== null) {
            $core['pricing'] = $this->pricing->toArray();
        }
        if ($this->campaign !== null) {
            $core['campaign'] = $this->campaign->toArray();
        }

        return $core;
    }
}

final class VoucherInstructionV2Factory
{
    /** @param array<string,mixed> $input */
    public static function make(array $input, EventCatalog $catalog): VoucherInstructionV2
    {
        Validator::make($input, [
            'execution.schema' => ['required', 'in:voucher.execution.v2'],
            'metadata.custom' => ['nullable', 'array'],
            'feedback' => ['nullable', 'array'],
            'feedback.email' => ['nullable', 'email'],
            'feedback.mobile' => ['nullable', 'string'],
            'feedback.webhooks' => ['nullable', 'array'],
            'feedback.webhooks.*.url' => ['required', 'url'],
            'feedback.webhooks.*.events' => ['required', 'array', 'min:1'],
            'feedback.webhooks.*.events.*' => ['required', 'string'],
            'presentation' => ['nullable', 'array'],
            'presentation.stamp' => ['nullable', 'array'],
            'presentation.stamp.design_id' => ['required_with:presentation.stamp', 'string'],
            'presentation.stamp.design_version' => ['required_with:presentation.stamp', 'integer', 'min:1'],
            'presentation.stamp.show_logo' => ['nullable', 'boolean'],
            'presentation.stamp.show_tagline' => ['nullable', 'boolean'],
            'presentation.stamp.claim_marker' => ['nullable', 'string'],
            'presentation.share' => ['nullable', 'array'],
            'presentation.share.title' => ['nullable', 'string'],
            'presentation.share.description' => ['nullable', 'string'],
            'presentation.share.image_source' => ['nullable', 'string'],
            'pricing' => ['nullable', 'array'],
            'pricing.quote_reference' => ['required_with:pricing', 'string'],
            'pricing.currency' => ['required_with:pricing', 'string', 'size:3'],
            'pricing.fee_bearer' => ['required_with:pricing', 'in:issuer,payer'],
            'pricing.quote_hash' => ['nullable', 'regex:/^[a-f0-9]{64}$/'],
            'campaign' => ['nullable', 'array'],
            'campaign.campaign_reference' => ['required_with:campaign', 'string'],
            'campaign.campaign_revision' => ['required_with:campaign', 'string'],
            'campaign.template_id' => ['required_with:campaign', 'string'],
            'campaign.template_version' => ['required_with:campaign', 'string'],
            'campaign.definition_hash' => ['required_with:campaign', 'regex:/^[a-f0-9]{64}$/'],
            'campaign.payment_recognition_reference' => ['nullable', 'string'],
        ])->validate();
        $execution = Contract::parse($input['execution']);
        if (! $execution instanceof ExecutionData) {
            throw new InvalidArgumentException('Expected v2 execution.');
        }
        $webhooks = [];
        foreach ($input['feedback']['webhooks'] ?? [] as $subscription) {
            $catalog->validate($subscription['events']);
            $webhooks[] = new WebhookSubscriptionData($subscription['url'], $subscription['events']);
        }
        $feedback = new FeedbackV2Data($input['feedback']['email'] ?? null, $input['feedback']['mobile'] ?? null, $webhooks);
        $presentation = null;
        if (isset($input['presentation'])) {
            $stamp = $input['presentation']['stamp'] ?? null;
            $share = $input['presentation']['share'] ?? null;
            $presentation = new PresentationData($stamp === null ? null : StampPresentationData::from($stamp), $share === null ? null : SharePresentationData::from($share));
        }
        $pricing = isset($input['pricing']) ? PricingDecisionData::from($input['pricing']) : null;
        $campaign = isset($input['campaign']) ? CampaignIdentityData::from($input['campaign']) : null;
        $core = $input;
        unset($core['execution'], $core['presentation'], $core['pricing'], $core['campaign']);
        $core['feedback'] = ['email' => $feedback->email, 'mobile' => $feedback->mobile, 'webhook' => null];
        $legacy = VoucherInstructionsData::createFromAttribs($core);
        if ($legacy->metadata !== null && array_key_exists('custom', $input['metadata'] ?? [])) {
            $legacy->metadata->custom = $input['metadata']['custom'];
        }

        return new VoucherInstructionV2($legacy, $execution, $feedback, $presentation, $pricing, $campaign);
    }
}
