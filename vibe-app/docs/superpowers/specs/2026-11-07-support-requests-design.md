# Support requests: conversation, statuses, Telegram assignment (design)

Date: 2026-11-07. Owner's request, approved in chat the same day. Extends the Support page and the Telegram bot that already exist (`SupportController`, `TelegramWebhookController`, `TelegramService`, `SupportMessage`, `SupportReply`).

## Goal

A school user opens a **Request** on the Support page and talks to Customer Services in a chat. Staff work the request from the Telegram group: take it, set its status, answer it, and see what is still pending. Photos and PDFs are kept in the user's own Google Drive, never on Railway. Finished conversations disappear from Railway five days after they are resolved.

## Words used

- **Request**: one support item (today "message"). Button: "Create request". List: "Your requests".
- **Type**: `general` (General help) or `subscription` (Subscription).
- **Statuses**: Pending (new) , For process, On hold, Approved, Resolved. Resolved ends the chat.

## Data

`support_messages` stays the requests table (no rename). New columns:

| Column | Meaning |
|---|---|
| `type` | `general` or `subscription`, default `general` |
| `subject` | short title, 120 characters |
| `status` | `pending`, `for_process`, `on_hold`, `approved`, `resolved`. Existing rows move: `open` to `pending`, `answered` to `for_process`, `closed` to `resolved` |
| `assigned_name`, `assigned_telegram_id` | who took it (first name only is shown to the user) |
| `resolved_at` | set when status becomes `resolved`, cleared if it is reopened |

`support_replies` becomes the conversation: new columns `sender` (`staff` or `user`) and `user_id` (set when `sender = user`).

New table `support_attachments`: `support_message_id`, `support_reply_id` (nullable), `user_id`, `drive_file_id`, `name`, `mime`, `size`. Only the link to the file, never the bytes.

## Web page

- Sidebar: **Support** link above School Settings, highlighted on the page.
- Layout: request list on the left (subject, status chip, last activity), the open conversation on the right as chat bubbles in time order (user on one side, Customer Services on the other). One column on narrow screens.
- "Create request": type, subject, message, optional attachments. Opens the conversation.
- The user can answer on any request that is not Resolved. A Resolved request is read-only and shows "Resolved on <date>, removed on <date + 5 days>".
- Master user keeps the all-requests view with school and sender; school users see only their own requests (unchanged tenant rule).

## Telegram

Staff act inside the existing Customer Services group. Each command works as a reply to the request's message, or with its number (`/process 12`).

- `/assign` takes the request; `/assign @name` gives it to another group member. First taker wins: the assignment is one atomic update that only succeeds while nobody holds it, and the loser is told who has it.
- `/process`, `/hold`, `/approve`, `/resolve` set the status; the bot confirms under the request message (for example "▶ Support #12 for process · Anna").
- `/queue` lists every request not resolved, grouped by status, unassigned first, with the assignee and the waiting time. `/mine` lists the requests assigned to the sender.
- A normal reply still answers the user; a message that cannot be tied to one request is never delivered (existing rule).
- A new request message starts with its type (🧾 for Subscription), number, school and name; no email.
- A user's follow-up is sent to the group as a reply under the request message.
- A submitted subscription payment proof posts a short notice (school, amount, reference); no file and no email.
- `Approved` is a label only. It does not change the subscription. Real approval stays on the Subscriptions page.

## Images

The user attaches JPG, PNG, WEBP or PDF files, up to 3 per message, 5 MB each. Each file goes to the user's own Google Drive (`Support` folder) and is shared with the administrator, the same way payment proofs already are (`GoogleDriveService`). If the user's Drive is not connected, the page asks them to connect it and the message is not lost. Railway stores only `support_attachments` rows. Staff do not send images from Telegram in this version.

## Retention

A daily command `support:purge` (scheduled in `routes/console.php`) deletes every request whose `resolved_at` is older than 5 days, with its replies and attachment rows. Files in the user's Drive are not touched. Each purge writes one audit log line with the count.

## Security and failure handling

- Validation on every input (lengths, types, `Rule::in` for type and status, attachment mimes and sizes); attachments checked by content type.
- A user can read and write only their own requests; the policy returns 404 for another user's request.
- Staff commands work only from the configured group chat (existing check); the webhook secret is unchanged.
- Throttle on creating requests and replies.
- Telegram or Drive failures never lose the message: it is saved, marked as not delivered, and logged.

## Delivery in three parts (each tested, shown, then deployed on the owner's word)

1. New page, sidebar link, request creation, conversation with user follow-ups, five statuses (web only).
2. Telegram: assign, status commands, `/queue`, `/mine`, subscription type and payment-proof notice.
3. Attachments in the user's Drive and the 5-day purge.

## Tests

Tests are written first for each part (migration mapping, policies, status rules, first-taker race, command parsing, queue output, attachment rules, purge cut-off) on top of the existing support tests. The full suite must stay green.

## Out of scope

Staff images from Telegram, one forum topic per request, activating a subscription from "Approved", email notifications.
