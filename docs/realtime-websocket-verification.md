# Realtime bằng WebSocket (Reverb): Tin nhắn và Thẻ đấu giá, kiểm chứng

Phạm vi đã chốt: (1) chat bỏ polling định kỳ, (2) thẻ đấu giá ở Trang chủ, khối Khám phá và trang /auctions cập nhật trực tiếp.
Hạ tầng dùng lại: Reverb 127.0.0.1:8080, `auctionEcho.js` (một kết nối, đếm consumer theo kênh), kênh `user.{id}` (private, chỉ chính chủ ACTIVE) và `auction.{id}` (public).

## Backend (không có migration, không đổi API REST)
- `Events/MessagingBroadcastEvent` (abstract): `ShouldBroadcastNow + ShouldDispatchAfterCommit + ShouldRescue`, giống các event đấu giá/video; phát trên `private-user.{id}` của từng người nhận, `broadcastAs` = tên lớp.
- `MessageSent`: phát cho **cả hai** participant (người gửi cần để đồng bộ tab khác). Payload `{conversation_id, message: MessageResource}`; không có email/tên.
- `ConversationRead`: chỉ phát cho người đọc, và chỉ khi checkpoint thật sự tăng. Payload `{conversation_id, last_read_message_id}`; dùng để badge ở tab/thiết bị khác tự giảm.
- `MessagingService::send/markRead` dispatch trong transaction hiện có (giữ nguyên thứ tự khóa); event chỉ chạy sau commit, rollback thì không phát, Reverb lỗi không làm hỏng giao dịch đã commit.

## Frontend
- `useMessaging`: subscribe `user.{id}` (dynamic import như Notifications, dùng chung kênh và kết nối).
  - Khi kênh sẵn sàng: dừng polling 4 giây, đồng bộ bù một lần (list + tin của hội thoại đang mở).
  - `MessageSent`: làm mới danh sách và, nếu đúng hội thoại đang mở, tải tin bằng `after_id` rồi đánh dấu đã đọc như trước. WebSocket chỉ là tín hiệu, REST vẫn là nguồn chuẩn, nên thứ tự và khoảng trống luôn đúng.
  - Tín hiệu đến khi đang tải tin không bị mất (cờ `again`, tải thêm một lần). Tin mà tab này đã có (tự gửi) thì bỏ qua.
  - `ConversationRead` làm mới danh sách/badge.
  - Fallback: khi `realtimeConnection` khác `connected` (mất mạng, Reverb tắt, chưa cấu hình key) thì polling 4 giây chạy lại như cũ. Khi kết nối lại, `subscribed` gọi đồng bộ bù.
  - Đổi tài khoản, đăng xuất hay unmount đều release kênh và watcher.
- `useAuctionCardLive` + `AuctionCard`: thẻ LIVE/SCHEDULED subscribe `auction.{id}`; thẻ ENDED/CANCELLED không subscribe.
  - `AuctionBidPlaced` cập nhật giá hiện tại và số lượt ra giá, giá nháy nền 1,2 giây (tắt khi `prefers-reduced-motion`).
  - `AuctionStatusChanged` cập nhật trạng thái/thời gian; phiên kết thúc thì release kênh.
  - Bỏ qua sự kiện cũ (`updated_at`), lượt ra giá không tăng, sai id. Dữ liệu danh sách mới hơn thắng bản vá realtime cũ.
  - Lần subscribe đầu không gọi API (dòng danh sách vừa tải); chỉ sau khi kết nối lại mới gọi `GET /auctions/{id}`.
  - Áp dụng cho mọi nơi dùng `AuctionCard` (HomeAuctions, HomeTabs/HomeDiscovery, AuctionsView), props không đổi.

## File
Mới: `backend/app/Events/{MessagingBroadcastEvent,MessageSent,ConversationRead}.php`, `backend/tests/Feature/MessagingRealtimeTest.php`, `frontend/src/composables/useAuctionCardLive.js`, `frontend/src/__tests__/AuctionCardLive.spec.js`.
Sửa: `backend/app/Services/MessagingService.php`, `frontend/src/composables/useMessaging.js`, `frontend/src/components/auction/AuctionCard.vue`, `frontend/src/__tests__/Messaging.spec.js`; một dòng ở `docs/final-architecture.md` và `docs/messaging-verification.md` (trước đó ghi "Messaging uses REST polling").

## Kết quả
| Cổng | Kết quả |
| --- | --- |
| `php artisan test` (secondhand_marketplace_test) | PASS, 511/511 (6671 assertions); +4 test mới |
| `vendor/bin/pint --test` (file đã đổi) | PASS |
| `npx vitest run` | PASS, 549/549 (52 file); +6 test chat realtime, +5 test thẻ đấu giá |
| `npx oxlint .` / `npx eslint .` | PASS, 0 lỗi / 0 lỗi |
| `npm run build` | PASS |

Test backend mới (4): người nhận đúng 2 kênh, payload không lộ email; dispatch sau commit/rescue; gửi bị từ chối hoặc rollback thì không phát; `ConversationRead` chỉ cho người đọc, chỉ khi checkpoint tăng.
Test frontend mới: dừng polling khi kênh sẵn sàng; tải theo `after_id` và đánh dấu đã đọc; hội thoại khác chỉ làm mới list; không mất tín hiệu khi đang tải; rớt kết nối thì quay lại polling rồi bắt kịp; không cấu hình thì vẫn polling; release khi đổi tài khoản/unmount. Thẻ: chỉ subscribe phiên mở; bỏ sự kiện cũ/sai id; kết thúc thì release; chỉ refetch sau reconnect; dòng danh sách mới hơn thắng.

## Chạy cục bộ
`php artisan reverb:start` (backend) + `VITE_REVERB_APP_KEY` khớp `REVERB_APP_KEY`, như README. Không có Reverb thì chat tự polling 4 giây, thẻ đấu giá giữ dữ liệu lúc tải trang.

## Còn mở
- Chưa chạy E2E hai trình duyệt với Reverb thật (tránh ghi tin nhắn vào DB dev); mới kiểm qua test có mock kênh.
- Không có chỉ báo "đang gõ", "đã xem" phía người kia, hay thông báo đẩy ngoài tab.
- Trang /auctions có nhiều thẻ thì mỗi thẻ mở 1 kênh public trên cùng 1 socket; không giới hạn số kênh.
- Broadcast vẫn đồng bộ sau commit, không queue/outbox; mất tín hiệu thì được bù khi kết nối lại hoặc khi tab hiện lại.
