<x-mail::message>
# Confirm your account deletion request

We received a request to delete the Delight account associated with this email address.

Confirming does not immediately delete your data. It sends a verified request to Delight support for manual review and fulfillment.

<x-mail::button :url="$verificationUrl">
Confirm deletion request
</x-mail::button>

This link expires in 60 minutes. If you did not request account deletion, you can ignore this email and your account will remain unchanged.

Questions? Contact [{{ config('mail.support_address') }}](mailto:{{ config('mail.support_address') }}).

— Delight by Orlando Labs
</x-mail::message>
