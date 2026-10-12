# Final sequence diagrams

Paths are `/api/v1` unless explicitly `/sanctum`. Simplified happy paths plus important rejection/commit boundaries; source: [controllers](../backend/app/Http/Controllers/Api/V1), [services](../backend/app/Services), [events](../backend/app/Events). Locks/Policies remain required beyond the steps drawn.

## A. Login / Sanctum

```mermaid
sequenceDiagram
    participant Vue as Vue / Axios via Vite
    participant API as Laravel / Sanctum
    participant DB as MySQL Users
    participant Session as File session store
    Vue->>API: GET /sanctum/csrf-cookie
    API->>Session: Establish session / CSRF
    API-->>Vue: Session and XSRF cookies
    Vue->>API: POST /api/v1/auth/login<br/>credentials / cookies / XSRF
    API->>API: Stateful/CSRF/rate-limit/FormRequest
    API->>DB: Check hashed password and ACTIVE account
    alt Valid credentials
        API->>Session: Authenticate and regenerate session ID
        API-->>Vue: AuthUserResource
        Vue->>Vue: ADMIN dashboard or safe intended User destination
        Vue->>API: GET /api/v1/auth/me with session
        API-->>Vue: Current active user
    else Invalid
        API-->>Vue: 422 generic credential error
    end
```

Role routing is UX; Admin APIs independently enforce ADMIN. Laravel 13 may validate trusted same-origin Fetch Metadata before token fallback. Logout invalidates session and regenerates CSRF token; no bearer token is issued by this flow.

## B. Cart → Checkout → COD

```mermaid
sequenceDiagram
    participant Buyer as Buyer / Vue
    participant API as Laravel
    participant Checkout as CheckoutService / OrderCreator
    participant DB as MySQL
    Buyer->>API: POST /cart/items (product_id, quantity)
    API->>DB: Owned cart write / availability checks
    API-->>Buyer: Cart resource
    Buyer->>API: POST /checkout<br/>owned address / COD / Idempotency-Key
    API->>Checkout: Validated actor/input
    Checkout->>DB: BEGIN, lock accounts/cart/address<br/>shops/categories/products
    Checkout->>DB: Check existing buyer + checkout_key / hash
    alt Same existing payload
        DB-->>Checkout: Original Order
    else New valid purchase
        Checkout->>Checkout: Recheck visibility / self-purchase / claim / stock<br/>BCMath total
        Checkout->>DB: Order + ShopOrders + snapshot items<br/>pending COD Payment
        Checkout->>DB: Decrement stock / SOLD if zero<br/>clear cart / audit
    end
    Checkout->>DB: COMMIT
    API-->>Buyer: Order resource (201 new / 200 replay)
```

Invalid state/stock/claim rejects without partial reservation; reused key with different payload returns 409. COD remains pending until Seller completion confirms collection under normal fulfillment rules.

## C. ZaloPay creation / callback

```mermaid
sequenceDiagram
    participant Vue as Buyer / Vue
    participant API as Laravel Checkout / ZaloPayService
    participant DB as MySQL
    participant ZP as ZaloPay Sandbox
    Vue->>API: Checkout ZALOPAY with UUID key
    API->>DB: Transaction: total / stock / Order / pending attempt<br/>COMMIT
    API->>DB: Short transaction claims gateway_requested_at
    API->>ZP: HTTPS create, KEY1 HMAC (outside DB transaction)
    ZP-->>API: Create response / order_url
    API->>DB: Locked pending-attempt update, safe URL only
    API-->>Vue: Order and pending payment / gateway URL
    Vue->>ZP: Open Sandbox checkout
    ZP-->>Vue: Browser return URL (not payment proof)
    ZP->>API: POST /payments/zalopay/callback (raw data + KEY2 MAC)
    API->>API: Verify MAC + app/reference/user/time<br/>amount/transaction
    API->>DB: Lock account/Order/ShopOrders/Payments
    alt Valid pending unchanged Order
        API->>DB: PAID + gateway transaction + audit, COMMIT
    else Identical duplicate transaction
        API->>DB: Preserve previous result
    else Verified late conflicting money
        API->>DB: reconciliation_required, do not reopen fulfillment
    end
    API-->>ZP: Gateway acknowledgement (invalid payload rejected)
    Vue->>API: Reload authoritative Order/payment status
    API-->>Vue: Safe persisted status
```

Scheduler/explicit query signs an HTTPS query request with KEY1 outside DB locks, then applies verified amount/transaction fields through the same locked confirmation. Callback transport requires public HTTPS tunnel locally. C1 manually verified creation/gateway only, not this external paid callback.

## D. Auction bid / lock / realtime

```mermaid
sequenceDiagram
    participant Buyer as Authenticated bidder
    participant API as Auction API / AuctionService
    participant DB as MySQL
    participant RV as Reverb
    participant Other as Other Echo browsers
    Buyer->>API: POST /auctions/{id}/bids (amount)
    API->>API: Session, throttle, validation, Policy
    API->>DB: BEGIN, lock account/shop/category/product<br/>then auction
    API->>API: Recheck actor / self-bid / visibility<br/>LIVE / server time
    API->>DB: Read previous bid under lock
    API->>API: BCMath minimum against current locked price
    API->>DB: Insert accepted bid, update current_price
    opt Different previous highest bidder
        API->>DB: Persist OUTBID with unique recipient/event key
    end
    API->>DB: COMMIT
    API->>RV: After commit: AuctionBidPlaced<br/>private OUTBID / Notification
    RV-->>Other: Public price/history, private owner notification
    API-->>Buyer: Accepted Bid resource
```

A concurrent bid waits and recalculates against the new price; insufficient/stale/end-time bids reject. Reverb outage is rescued; DB write stays authoritative.

## E. Winner → checkout → Order

```mermaid
sequenceDiagram
    participant Scheduler as Scheduler / auctions:sync
    participant Service as Auction services
    participant DB as MySQL
    participant Winner as Winning Buyer / Vue
    Scheduler->>Service: Finalize after actual ends_at
    Service->>DB: Lock auction, choose highest accepted bid
    Service->>DB: ENDED / winner / deadline<br/>persist notifications / COMMIT
    Service-->>Winner: Best-effort private AUCTION_WON (DB history available)
    Winner->>Service: GET /auctions/{id}/checkout
    Service->>Service: Winner Policy, safe context/deadline
    Service-->>Winner: Winning price, addresses, existing Order if any
    Winner->>Service: POST checkout (owned address, method, UUID)
    Service->>DB: BEGIN, lock context/auction<br/>recheck winner/result/deadline
    alt Existing winner Order, matching payload
        DB-->>Service: Original Order, no new reservation
    else Eligible unconsumed winner claim
        Service->>DB: Reserve one unit / winning bid price<br/>Order / ShopOrder / item / Payment
        Service->>DB: winner_order_id + settled_at, notifications
    end
    Service->>DB: COMMIT
    Service-->>Winner: Ordinary Order (201 / 200)
```

New checkout fails after deadline or unavailable stock/context; another user is forbidden. ZaloPay starts after the Order transaction. Lifecycle ENDED is independent of Order/payment status; no fallback winner.

## F. Buyer/Seller messaging

```mermaid
sequenceDiagram
    participant Buyer as Buyer ChatDrawer
    participant API as Conversation API / MessagingService
    participant DB as MySQL
    participant Seller as Seller ChatDrawer
    Buyer->>API: POST /conversations (product_id or seller context)
    API->>DB: Lock Buyer<br/>validate public Shop/Product / no self-chat
    API->>DB: Reuse context_key or create conversation<br/>two participants
    API-->>Buyer: Conversation resource
    Buyer->>API: POST /conversations/{id}/messages (trimmed text)
    API->>DB: Lock conversation / authorize participant<br/>insert / last_message_at
    API-->>Buyer: Message resource
    Seller->>API: Poll list / active messages around every 4 seconds
    API->>DB: Participant-scoped history/unread query
    API-->>Seller: New message + unread count
    Seller->>API: POST /conversations/{id}/read (last_message_id)
    API->>DB: Validate message belongs<br/>monotonic participant read cursor
    Seller->>API: Send participant-authorized reply
    Buyer->>API: Next active polling refresh
    API-->>Buyer: Reply
```

No WebSocket messaging. Timers pause when page is inactive and clean up on unmount. Conversation survives product unavailability; unsafe current product context is omitted.

## G. Video comment → DB → Reverb

```mermaid
sequenceDiagram
    participant A as Authenticated Theater A
    participant API as VideoCommentService
    participant DB as MySQL
    participant RV as Reverb
    participant B as Theater B / Echo
    A->>API: POST /videos/{id}/comments (body)
    API->>API: Trim/validate ≤500, throttle, Policy
    API->>DB: BEGIN, lock account/shop/category<br/>product/video
    API->>API: Recheck READY/public visibility
    API->>DB: Insert PUBLISHED comment, COMMIT
    API->>RV: After commit VideoCommentCreated if still public
    RV-->>B: Safe payload on video.{id}
    B->>B: Merge/dedup by comment ID, escaped display
    API-->>A: Comment resource
    opt Reconnect/resume
        B->>API: REST refresh/cursor history
        API-->>B: Current published history
    end
```

Author/owning Seller/Admin hide follows locked Policy + audit and committed VideoCommentHidden; clients remove by ID. Hidden Product/Video blocks public history/new comments; public channels never contain private email/session data.
