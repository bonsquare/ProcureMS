<?php

namespace App\Services;

use App\Models\PaymentProof;
use App\Models\School;

/** The payment proof card in the Telegram group: its text (always with the current decision and remark) and its buttons. */
final class PaymentProofNotice
{
    private const STATUS = [
        PaymentProof::PENDING => ['🟡', 'Waiting for review'],
        PaymentProof::FOR_PROCESS => ['⏳', 'For process'],
        PaymentProof::APPROVED => ['✅', 'Approved'],
        PaymentProof::REJECTED => ['❌', 'Declined'],
    ];

    /** The sender, the amount (with the discount), the account, the date, the reference, the remarks and the Drive link of the proof. */
    public static function text(PaymentProof $proof, School $school): string
    {
        $amount = '₱'.number_format((float) $proof->amount_to_pay, 2);
        if ((float) $proof->discount_amount > 0) {
            $amount .= ' (price ₱'.number_format((float) $proof->list_price, 2).' − discount ₱'.number_format((float) $proof->discount_amount, 2).')';
        }
        [$icon, $label] = self::STATUS[$proof->status] ?? self::STATUS[PaymentProof::PENDING];

        return "🧾 New Payment Proof · {$school->name}\n"
            ."🎫 {$proof->number}\n"
            ."{$icon} Status: {$label}\n"
            .($proof->assigned_name ? "👥 Assigned to: {$proof->assigned_name}\n" : '')
            .($proof->reject_reason && $proof->status !== PaymentProof::PENDING ? "💬 Remark to the school: {$proof->reject_reason}\n" : '')
            .'🏫 School: '.$school->name.($school->code ? " ({$school->code})" : '')."\n"
            ."👤 Sent by: {$proof->submitted_by_name} (@{$proof->submitted_by_username})\n"
            ."💰 Amount: {$amount}\n"
            ."🏦 Account used: {$proof->bank_account_used}\n"
            .'🕒 Paid on: '.$proof->transacted_at->format('M d, Y g:i A')."\n"
            ."🔢 Reference: {$proof->reference}\n"
            .($proof->remarks ? "📝 Remarks: {$proof->remarks}\n" : '')
            ."\nProof of payment\n📎 {$proof->file_name} — {$proof->drive_link}\n"
            ."\nThe file is in the sender's Google Drive, shared with the administrator email only.";
    }

    /** Approve and Decline while the proof is open; For process only until someone has put it there. Nothing once it is decided. */
    public static function keyboard(PaymentProof $proof): array
    {
        if (! $proof->isPending()) {
            return [];
        }
        $button = fn (string $label, string $action) => ['text' => $label, 'callback_data' => "p:{$proof->id}:{$action}"];
        $rows = [[$button($proof->assigned_name ? '🔁 Reassign' : '👥 Assign to staff', 'assign')], [$button('✅ Approve', 'approve'), $button('❌ Decline', 'decline')]];
        if ($proof->status === PaymentProof::PENDING) {
            $rows[] = [$button('⏳ For process', 'process')];
        }

        return $rows;
    }

    /**
     * The staff to pick from (two per row), then Back.
     *
     * @param  array<int, string>  $staff  Telegram id => name
     */
    public static function staffKeyboard(PaymentProof $proof, array $staff): array
    {
        $rows = [];
        foreach (array_chunk(array_keys($staff), 2) as $pair) {
            $rows[] = array_map(fn (int $id) => ['text' => '👤 '.$staff[$id], 'callback_data' => "p:{$proof->id}:to:{$id}"], $pair);
        }
        $rows[] = [['text' => '⬅️ Back', 'callback_data' => "p:{$proof->id}:back"]];

        return $rows;
    }

    /** The question asked before an approval is made. */
    public static function confirmKeyboard(PaymentProof $proof): array
    {
        return [[['text' => '✅ Yes, approve now', 'callback_data' => "p:{$proof->id}:yes"], ['text' => '⬅️ Back', 'callback_data' => "p:{$proof->id}:back"]]];
    }
}
