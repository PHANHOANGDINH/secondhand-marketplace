# Final database ERD

Source of truth: all applied [migrations](../backend/database/migrations) and current [models](../backend/app/Models), including additive ZaloPay/Auction/Video migrations. Attribute blocks show **selected actual columns**, not every timestamp/address field or a replacement DDL. DECIMAL money is (15,2). Optionality includes historical zero-child rows; domain services enforce valid transaction composition.

Solid lines denote declared FK relationships; dotted lines are logical/polymorphic links without SQL FKs. Generated active_product_id/primary_product_id are unique projections, not separate parent references. PK/FK/UK annotate individual keys; composite keys are explained below. No products.sku, orders.status or auctions.settlement_status column is invented.

## Whole business relationship map

Reference-only overview: this cyclic cross-domain graph is intentionally dense and should be opened at native size with pan/zoom. For defense/projector use, show the six domain diagrams below, not a fit-to-page thumbnail of the full map. Cross-domain reference entities show only a few identifiers in those views.

```mermaid
erDiagram
    users ||--o{ addresses : owns
    users ||--o| shops : owns
    categories |o--o{ categories : parent
    shops ||--o{ products : lists
    categories ||--o{ products : categorizes
    products ||--o{ product_images : images
    users ||--o| carts : has
    carts ||--o{ cart_items : contains
    products ||--o{ cart_items : references
    users ||--o{ orders : buyer
    orders ||--o{ shop_orders : splits
    shops ||--o{ shop_orders : fulfills
    shop_orders ||--o{ order_items : items
    products ||--o{ order_items : snapshot_source
    orders ||--o{ payments : attempts
    users ||--o{ reviews : author
    products ||--o{ reviews : reviewed
    order_items ||--o| reviews : purchase_review
    users |o--o{ audit_logs : actor
    products ||--o{ auctions : history
    users ||--o{ auctions : seller
    users |o--o{ auctions : winner
    auctions ||--o{ bids : accepted
    users ||--o{ bids : bidder
    bids |o--o| auctions : winning_bid
    orders |o--o| auctions : winner_order
    auctions |o--o| order_items : settlement_item
    users ||--o{ conversations : buyer
    users ||--o{ conversations : seller
    products |o--o{ conversations : optional_context
    conversations ||--o{ conversation_participants : members
    users ||--o{ conversation_participants : participates
    conversations ||--o{ messages : history
    users ||--o{ messages : sender
    users ||..o{ notifications : polymorphic_owner
    products ||--o{ product_videos : videos
    users ||--o{ product_videos : uploader
    product_videos ||--o{ video_comments : comments
    users ||--o{ video_comments : author
    users ||..o{ password_reset_tokens : email_lookup_no_FK
    users |o..o{ sessions : optional_no_FK
    users ||..o{ personal_access_tokens : polymorphic_tokenable
```

## Identity

```mermaid
erDiagram
    users {
        bigint id PK
        string name
        string email UK
        string role
        string status
        datetime deleted_at
    }
    addresses {
        bigint id PK
        bigint user_id FK
        string recipient_name
        string address_line
        boolean is_default
    }
    password_reset_tokens {
        string email PK
        string token
        datetime created_at
    }
    sessions {
        string id PK
        bigint user_id
        string ip_address
        text payload
        int last_activity
    }
    personal_access_tokens {
        bigint id PK
        string tokenable_type
        bigint tokenable_id
        string token UK
        datetime expires_at
    }
    users ||--o{ addresses : owns
    users ||..o{ password_reset_tokens : email_lookup_no_FK
    users |o..o{ sessions : optional_no_FK
    users ||..o{ personal_access_tokens : polymorphic_tokenable
```

## Marketplace

```mermaid
erDiagram
    users {
        bigint id PK
        string name
        string email UK
        string role
        string status
        datetime deleted_at
    }
    shops {
        bigint id PK
        bigint owner_id FK,UK
        string name
        string slug UK
        string status
        datetime deleted_at
    }
    categories {
        bigint id PK
        bigint parent_id FK
        string name
        string slug UK
        string status
    }
    products {
        bigint id PK
        bigint shop_id FK
        bigint category_id FK
        string name
        string slug UK
        decimal price
        int stock_quantity
        string condition
        string status
        datetime deleted_at
    }
    product_images {
        bigint id PK
        bigint product_id FK
        string image_path
        boolean is_primary
        int sort_order
    }
    users ||--o| shops : owns
    categories |o--o{ categories : parent
    shops ||--o{ products : lists
    categories ||--o{ products : categorizes
    products ||--o{ product_images : images
```

## Transactions

```mermaid
erDiagram
    users {
        bigint id PK
        string name
        string role
    }
    shops {
        bigint id PK
        bigint owner_id FK,UK
        string name
    }
    products {
        bigint id PK
        bigint shop_id FK
        bigint category_id FK
        string name
    }
    carts {
        bigint id PK
        bigint user_id FK,UK
    }
    cart_items {
        bigint id PK
        bigint cart_id FK
        bigint product_id FK
        int quantity
    }
    orders {
        bigint id PK
        bigint buyer_id FK
        string order_code UK
        string checkout_key
        string request_hash
        string shipping_address_line
        decimal subtotal
        decimal total_amount
        string payment_method
    }
    shop_orders {
        bigint id PK
        bigint order_id FK
        bigint shop_id FK
        string status
        decimal total_amount
        datetime stock_restored_at
    }
    order_items {
        bigint id PK
        bigint shop_order_id FK
        bigint product_id FK
        bigint auction_id FK,UK
        string product_name
        string product_image
        decimal product_price
        int quantity
        decimal subtotal
    }
    payments {
        bigint id PK
        bigint order_id FK
        int attempt_number
        string method
        decimal amount
        string status
        string transaction_reference UK
        string gateway_transaction_id
        string request_key
        string order_url
        datetime expires_at
        boolean reconciliation_required
    }
    reviews {
        bigint id PK
        bigint user_id FK
        bigint product_id FK
        bigint order_item_id FK,UK
        int rating
        text comment
        string status
    }
    audit_logs {
        bigint id PK
        bigint actor_id FK
        string entity_type
        bigint entity_id
        string action
        string from_status
        string to_status
        datetime created_at
    }
    users ||--o| shops : owns
    shops ||--o{ products : lists
    users ||--o| carts : has
    carts ||--o{ cart_items : contains
    products ||--o{ cart_items : references
    users ||--o{ orders : buyer
    orders ||--o{ shop_orders : splits
    shops ||--o{ shop_orders : fulfills
    shop_orders ||--o{ order_items : items
    products ||--o{ order_items : snapshot_source
    orders ||--o{ payments : attempts
    users ||--o{ reviews : author
    products ||--o{ reviews : reviewed
    order_items ||--o| reviews : purchase_review
    users |o--o{ audit_logs : actor
```

## Auctions

```mermaid
erDiagram
    users {
        bigint id PK
        string name
        string role
    }
    products {
        bigint id PK
        bigint shop_id FK
        bigint category_id FK
        string name
    }
    auctions {
        bigint id PK
        bigint product_id FK
        bigint seller_id FK
        decimal start_price
        decimal current_price
        decimal bid_increment
        datetime starts_at
        datetime ends_at
        string status
        bigint winner_user_id FK
        bigint winning_bid_id FK
        bigint active_product_id UK
        datetime winner_checkout_expires_at
        bigint winner_order_id FK,UK
        datetime settled_at
        datetime winner_checkout_reminded_at
    }
    bids {
        bigint id PK
        bigint auction_id FK
        bigint user_id FK
        decimal amount
        datetime created_at
    }
    orders {
        bigint id PK
        bigint buyer_id FK
        string order_code UK
        string checkout_key
        string request_hash
        string shipping_address_line
        decimal subtotal
        decimal total_amount
        string payment_method
    }
    order_items {
        bigint id PK
        bigint shop_order_id FK
        bigint product_id FK
        bigint auction_id FK,UK
        string product_name
        string product_image
        decimal product_price
        int quantity
        decimal subtotal
    }
    users ||--o{ orders : buyer
    products ||--o{ order_items : snapshot_source
    products ||--o{ auctions : history
    users ||--o{ auctions : seller
    users |o--o{ auctions : winner
    auctions ||--o{ bids : accepted
    users ||--o{ bids : bidder
    bids |o--o| auctions : winning_bid
    orders |o--o| auctions : winner_order
    auctions |o--o| order_items : settlement_item
```

## Communication

```mermaid
erDiagram
    users {
        bigint id PK
        string name
        string role
    }
    products {
        bigint id PK
        bigint shop_id FK
        bigint category_id FK
        string name
    }
    conversations {
        bigint id PK
        bigint buyer_id FK
        bigint seller_id FK
        bigint product_id FK
        string context_key UK
        datetime last_message_at
    }
    conversation_participants {
        bigint id PK
        bigint conversation_id FK
        bigint user_id FK
        bigint last_read_message_id
    }
    messages {
        bigint id PK
        bigint conversation_id FK
        bigint sender_id FK
        string body
        datetime created_at
    }
    notifications {
        uuid id PK
        string type
        string dedup_key UK
        string notifiable_type
        bigint notifiable_id
        text data
        datetime read_at
    }
    users ||--o{ conversations : buyer
    users ||--o{ conversations : seller
    products |o--o{ conversations : optional_context
    conversations ||--o{ conversation_participants : members
    users ||--o{ conversation_participants : participates
    conversations ||--o{ messages : history
    users ||--o{ messages : sender
    users ||..o{ notifications : polymorphic_owner
```

## Video and content

```mermaid
erDiagram
    users {
        bigint id PK
        string name
        string role
    }
    products {
        bigint id PK
        bigint shop_id FK
        bigint category_id FK
        string name
    }
    product_videos {
        bigint id PK
        bigint product_id FK
        bigint uploaded_by FK
        string title
        string path UK
        string mime_type
        bigint file_size
        int duration_seconds
        string poster_path
        int sort_order
        boolean is_primary
        string status
        bigint primary_product_id UK
    }
    video_comments {
        bigint id PK
        bigint product_video_id FK
        bigint user_id FK
        text body
        string status
        datetime created_at
    }
    products ||--o{ product_videos : videos
    users ||--o{ product_videos : uploader
    product_videos ||--o{ video_comments : comments
    users ||--o{ video_comments : author
```

## Constraints and schema interpretation

- Shop owner and Cart user are unique. Category parent is nullable; service enforces maximum three levels/cycle prevention. Address default is serialized by account locking, not a unique is_default index.
- Products require positive price and enum condition/status. Image primary is normalized by locked services, not a generated unique-image column. Soft deletes apply to Users, Shops and Products; transaction FKs preserve references.
- Composite uniqueness: cart_items(cart_id, product_id), orders(buyer_id, checkout_key), shop_orders(order_id, shop_id), order_items(shop_order_id, product_id), payments(order_id, attempt_number), payments(order_id, request_key), payments(method, gateway_transaction_id), conversation_participants(conversation_id, user_id). A nullable gateway transaction or request key is not globally unique by itself.
- Order has shipping/item/payment snapshots; fulfillment status lives in ShopOrders and payment status in Payments. Fees/discount fields exist with current zero defaults; no coupon feature implied. VNPAY and REFUNDED schema values persist historically; runtime delivers COD/ZALOPAY, not VNPAY/refund processing.
- Auction active_product_id is generated only for SCHEDULED/LIVE and uniquely blocks another unfinished auction. Unexpired winner claims additionally block creation/fixed buying through service predicates. Winning-bid FK is composite `(auctions.id, winning_bid_id) → bids(auction_id, id)`, so another auction's bid cannot be selected. winner_order_id and order_items.auction_id are nullable unique links. Settlement state is derived; there is no settlement enum column.
- Notifications use notifiable_type/notifiable_id polymorphism to User, unique dedup_key and serialized data. Audit entity_type/entity_id is a logical entity reference, not a universal FK. Participant last_read_message_id is a validated monotonic cursor (0 initially), not an FK to Messages.
- Video primary_product_id is generated only for READY primary and unique. PROCESSING/FAILED enum support does not mean transcoding exists. Comment status is PUBLISHED/HIDDEN.
- password_reset_tokens identifies email without a User FK. sessions.user_id is indexed but not constrained. Personal access tokens are framework schema support, not issued by current SPA auth. Local sessions use files, not the sessions table.
- Framework migrations also contain cache, cache_locks, jobs, job_batches, failed_jobs and migrations bookkeeping. They are infrastructure rather than marketplace business relationships and are omitted from the business ERDs. Current after-commit broadcasts do not require a queue worker.

[Architecture](final-architecture.md) explains runtime use; [auction states](auction-state-machines.md) distinguishes derived state from persisted fields.
