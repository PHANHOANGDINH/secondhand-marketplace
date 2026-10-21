"""Generate Merise/Chen-style conceptual ER diagrams (SVG) for Passdi."""
import math
import sys
from html import escape

OUT = sys.argv[1]
CW = 6.9  # approx char width at 12.5px Segoe UI
LINE = 16
HEAD = 42

INK = '#12302A'
LINE_C = '#1F6F5C'
ENT_FILL = '#FFFFFF'
ENT_HEAD = '#D7EFE6'
REL_FILL = '#FFF7E8'
REL_STROKE = '#B45309'
MUTED = '#4A615B'


class Diagram:
    def __init__(self, title, w, h):
        self.title, self.w, self.h = title, w, h
        self.ent, self.rel, self.links, self.extra = {}, {}, [], []

    def e(self, key, name, table, attrs, cx, cy):
        width = max(len(name) * 8.2, len(table) * CW, max(len(a.strip('*')) for a in attrs) * CW) + 28
        height = HEAD + len(attrs) * LINE + 10
        self.ent[key] = dict(name=name, table=table, attrs=attrs, cx=cx, cy=cy, w=width, h=height)

    def r(self, key, name, cx, cy, attrs=()):
        attrs = list(attrs)
        if attrs:
            inner_w = max(len(name) * 7.6, max(len(a) for a in attrs) * CW)
            rx = inner_w / 2 + 26
            ry = (24 + len(attrs) * 15) / 2 + 12
        else:
            rx = len(name) * 3.8 + 20
            ry = 19
        self.rel[key] = dict(name=name, attrs=attrs, cx=cx, cy=cy, rx=rx, ry=ry)

    def l(self, ent, rel, card, via=(), role='', side=1, dist=24):
        self.links.append(dict(ent=ent, rel=rel, card=card, via=list(via), role=role, side=side, dist=dist))

    def note(self, x, y, text, size=12, weight=400, color=MUTED, anchor='start'):
        self.extra.append(
            f'<text x="{x}" y="{y}" font-size="{size}" font-weight="{weight}" fill="{color}" '
            f'text-anchor="{anchor}">{escape(text)}</text>'
        )

    # geometry -----------------------------------------------------------
    @staticmethod
    def clip_rect(e, px, py):
        dx, dy = px - e['cx'], py - e['cy']
        if dx == 0 and dy == 0:
            return e['cx'], e['cy']
        tx = (e['w'] / 2) / abs(dx) if dx else math.inf
        ty = (e['h'] / 2) / abs(dy) if dy else math.inf
        t = min(tx, ty)
        return e['cx'] + dx * t, e['cy'] + dy * t

    @staticmethod
    def clip_ellipse(r, px, py):
        dx, dy = px - r['cx'], py - r['cy']
        t = 1 / math.sqrt((dx / r['rx']) ** 2 + (dy / r['ry']) ** 2)
        return r['cx'] + dx * t, r['cy'] + dy * t

    def svg(self):
        out = [
            f'<svg xmlns="http://www.w3.org/2000/svg" width="{self.w}" height="{self.h}" '
            f'viewBox="0 0 {self.w} {self.h}" font-family="Segoe UI, Arial, sans-serif">',
            f'<rect width="100%" height="100%" fill="#FFFFFF"/>',
            f'<text x="24" y="34" font-size="20" font-weight="700" fill="{INK}">{escape(self.title)}</text>',
        ]
        labels = []
        for k in self.links:
            e, r = self.ent[k['ent']], self.rel[k['rel']]
            pts = [(e['cx'], e['cy'])] + k['via'] + [(r['cx'], r['cy'])]
            a = self.clip_rect(e, *pts[1])
            b = self.clip_ellipse(r, *pts[-2])
            path = [a] + k['via'] + [b]
            d = 'M' + ' L'.join(f'{x:.1f},{y:.1f}' for x, y in path)
            out.append(f'<path d="{d}" fill="none" stroke="{LINE_C}" stroke-width="1.4"/>')
            # cardinality label near the entity end
            (x0, y0), (x1, y1) = path[0], path[1]
            ln = math.hypot(x1 - x0, y1 - y0) or 1
            ux, uy = (x1 - x0) / ln, (y1 - y0) / ln
            dist = min(k['dist'], ln / 2)
            nx, ny = -uy * k['side'], ux * k['side']
            lx, ly = x0 + ux * dist + nx * 12, y0 + uy * dist + ny * 12 + 4
            text = (k['role'] + ' ' if k['role'] else '') + k['card']
            labels.append(
                f'<text x="{lx:.1f}" y="{ly:.1f}" font-size="12.5" font-weight="600" fill="{INK}" '
                f'text-anchor="middle" stroke="#fff" stroke-width="4" paint-order="stroke">{escape(text)}</text>'
            )
        for r in self.rel.values():
            out.append(
                f'<ellipse cx="{r["cx"]}" cy="{r["cy"]}" rx="{r["rx"]:.1f}" ry="{r["ry"]:.1f}" '
                f'fill="{REL_FILL}" stroke="{REL_STROKE}" stroke-width="1.5"/>'
            )
            if r['attrs']:
                top = r['cy'] - r['ry'] + 22
                out.append(
                    f'<text x="{r["cx"]}" y="{top}" font-size="13" font-weight="700" fill="{INK}" '
                    f'text-anchor="middle">{escape(r["name"])}</text>'
                )
                out.append(
                    f'<line x1="{r["cx"] - r["rx"] * 0.82:.1f}" y1="{top + 8}" x2="{r["cx"] + r["rx"] * 0.82:.1f}" '
                    f'y2="{top + 8}" stroke="{REL_STROKE}" stroke-width="1"/>'
                )
                for i, a in enumerate(r['attrs']):
                    out.append(
                        f'<text x="{r["cx"]}" y="{top + 24 + i * 15}" font-size="12" fill="{INK}" '
                        f'text-anchor="middle">{escape(a)}</text>'
                    )
            else:
                out.append(
                    f'<text x="{r["cx"]}" y="{r["cy"] + 4.5}" font-size="13" font-weight="700" fill="{INK}" '
                    f'text-anchor="middle">{escape(r["name"])}</text>'
                )
        for e in self.ent.values():
            x, y = e['cx'] - e['w'] / 2, e['cy'] - e['h'] / 2
            out.append(
                f'<rect x="{x:.1f}" y="{y:.1f}" width="{e["w"]:.1f}" height="{e["h"]:.1f}" rx="6" '
                f'fill="{ENT_FILL}" stroke="{INK}" stroke-width="1.6"/>'
            )
            out.append(
                f'<path d="M{x:.1f},{y + HEAD:.1f} V{y + 6:.1f} Q{x:.1f},{y:.1f} {x + 6:.1f},{y:.1f} '
                f'H{x + e["w"] - 6:.1f} Q{x + e["w"]:.1f},{y:.1f} {x + e["w"]:.1f},{y + 6:.1f} V{y + HEAD:.1f} Z" '
                f'fill="{ENT_HEAD}" stroke="none"/>'
            )
            out.append(
                f'<line x1="{x:.1f}" y1="{y + HEAD:.1f}" x2="{x + e["w"]:.1f}" y2="{y + HEAD:.1f}" '
                f'stroke="{INK}" stroke-width="1.2"/>'
            )
            out.append(
                f'<text x="{e["cx"]}" y="{y + 19}" font-size="14" font-weight="700" fill="{INK}" '
                f'text-anchor="middle">{escape(e["name"])}</text>'
            )
            out.append(
                f'<text x="{e["cx"]}" y="{y + 35}" font-size="11.5" font-style="italic" fill="{MUTED}" '
                f'text-anchor="middle">{escape(e["table"])}</text>'
            )
            for i, a in enumerate(e['attrs']):
                pk = a.startswith('*')
                label = a.strip('*')
                deco = ' text-decoration="underline" font-weight="700"' if pk else ''
                fill = MUTED if label == '…' else INK
                out.append(
                    f'<text x="{x + 12:.1f}" y="{y + HEAD + 17 + i * LINE:.1f}" font-size="12.5" '
                    f'fill="{fill}"{deco}>{escape(label)}</text>'
                )
        out += labels + self.extra
        out.append('</svg>')
        return '\n'.join(out)


USER_SHORT = ['*id*', 'name', 'email', 'role', '…']
PROD_SHORT = ['*id*', 'name', 'price', 'status', '…']

diagrams = {}

# 1 -------------------------------------------------------------------------
d = Diagram('Sơ đồ 1 · Tài khoản, gian hàng, danh mục và sản phẩm', 1560, 1090)
d.e('U', 'NGUOI_DUNG', 'users', ['*id*', 'name', 'email', 'email_verified_at', 'password', 'temporary_password',
    'temporary_password_expires_at', 'must_change_password', 'phone', 'avatar_path', 'role', 'status', 'deleted_at'], 200, 480)
d.e('A', 'DIA_CHI', 'addresses', ['*id*', 'recipient_name', 'phone', 'address_line', 'ward', 'district',
    'province', 'country_code', 'is_default'], 200, 150)
d.e('M', 'THANH_VIEN', 'users · role = USER', ['mua, bán, nhắn tin, đấu giá'], 125, 820)
d.e('Q', 'QUAN_TRI_VIEN', 'users · role = ADMIN', ['duyệt, xử lý, đối soát'], 335, 820)
d.e('S', 'GIAN_HANG', 'shops', ['*id*', 'name', 'slug', 'description', 'province_code', 'logo_path', 'status',
    'moderation_reason', 'deleted_at'], 650, 150)
d.e('C', 'DANH_MUC', 'categories', ['*id*', 'name', 'slug', 'description', 'status'], 1090, 150)
d.e('P', 'SAN_PHAM', 'products', ['*id*', 'name', 'slug', 'description', 'price', 'compare_at_price', 'stock_quantity',
    'condition', 'status', 'moderation_reason', 'published_at', 'deleted_at'], 870, 570)
d.e('I', 'ANH_SAN_PHAM', 'product_images', ['*id*', 'image_path', 'alt_text', 'is_primary', 'sort_order'], 1360, 410)
d.e('V', 'VIDEO_SAN_PHAM', 'product_videos', ['*id*', 'title', 'description', 'path', 'mime_type', 'file_size',
    'duration_seconds', 'poster_path', 'sort_order', 'is_primary', 'status'], 1360, 720)
d.e('B', 'BINH_LUAN_VIDEO', 'video_comments', ['*id*', 'body', 'status', 'created_at'], 1360, 1005)
d.r('co_dc', 'Có', 200, 300)
d.l('U', 'co_dc', '0,n'); d.l('A', 'co_dc', '1,1')
d.r('so_huu', 'Sở hữu', 430, 250)
d.l('U', 'so_huu', '0,1'); d.l('S', 'so_huu', '1,1', side=-1)
d.r('dg_gh', 'Đánh giá', 440, 440, ['rating', 'comment', 'status', 'moderation_reason'])
d.l('U', 'dg_gh', '0,n', side=-1); d.l('S', 'dg_gh', '0,n')
d.r('dang_ban', 'Đăng bán', 760, 345)
d.l('S', 'dang_ban', '0,n', side=-1); d.l('P', 'dang_ban', '1,1')
d.r('thuoc', 'Thuộc', 990, 345)
d.l('C', 'thuoc', '0,n'); d.l('P', 'thuoc', '1,1', side=-1)
d.r('la_con', 'Là con của', 1340, 150)
d.l('C', 'la_con', '0,1', via=[(1250, 105)], role='con')
d.l('C', 'la_con', '0,n', via=[(1250, 195)], role='cha', side=-1)
d.r('co_anh', 'Có ảnh', 1130, 455)
d.l('P', 'co_anh', '0,n'); d.l('I', 'co_anh', '1,1', side=-1)
d.r('co_video', 'Có video', 1130, 660)
d.l('P', 'co_video', '0,n', side=-1); d.l('V', 'co_video', '1,1')
d.r('tai_len', 'Tải lên', 700, 880)
d.l('U', 'tai_len', '0,n', side=-1, dist=40); d.l('V', 'tai_len', '1,1', side=-1)
d.r('co_bl', 'Có', 1360, 880)
d.l('V', 'co_bl', '0,n'); d.l('B', 'co_bl', '1,1')
d.r('viet_bl', 'Viết', 600, 1005)
d.l('U', 'viet_bl', '0,n'); d.l('B', 'viet_bl', '1,1')
# specialization
d.extra.append(f'<path d="M200,{480 + 125} L200,690" stroke="{INK}" stroke-width="1.6"/>')
d.extra.append(f'<circle cx="200" cy="705" r="15" fill="#fff" stroke="{INK}" stroke-width="1.6"/>')
d.extra.append(f'<text x="200" y="710" font-size="13" font-weight="700" fill="{INK}" text-anchor="middle">d</text>')
d.extra.append(f'<path d="M190,716 L125,793 M210,716 L335,793" stroke="{INK}" stroke-width="1.6" fill="none"/>')
d.note(222, 690, 'Phân loại theo role', 12, 600, INK)
d.note(24, 1070, 'Thuộc tính gạch chân là khóa chính. Khóa ngoại không ghi trong thực thể: chúng được biểu diễn bằng mối kết hợp. '
       '"d" = chuyên biệt hóa rời nhau (một người dùng là THANH_VIEN hoặc QUAN_TRI_VIEN).')
diagrams['1-tai-khoan-san-pham'] = d

# 2 -------------------------------------------------------------------------
d = Diagram('Sơ đồ 2 · Giỏ hàng, đơn hàng, thanh toán và voucher', 1840, 1200)
d.e('U', 'NGUOI_DUNG', 'users', USER_SHORT, 560, 160)
d.e('S', 'GIAN_HANG', 'shops', ['*id*', 'name', 'status', '…'], 960, 260)
d.e('G', 'GIO_HANG', 'carts', ['*id*', 'created_at', 'updated_at'], 1720, 290)
d.e('P', 'SAN_PHAM', 'products', PROD_SHORT, 1720, 660)
d.e('O', 'DON_HANG', 'orders', ['*id*', 'order_code', 'checkout_key', 'request_hash', 'shipping_recipient_name',
    'shipping_phone', 'shipping_address_line', 'shipping_ward', 'shipping_district', 'shipping_province',
    'shipping_country_code', 'subtotal', 'shipping_fee', 'discount_amount', 'platform_voucher_code', 'total_amount',
    'currency', 'payment_method', 'created_at'], 560, 600)
d.e('T', 'THANH_TOAN', 'payments', ['*id*', 'attempt_number', 'method', 'amount', 'currency', 'status',
    'transaction_reference', 'gateway_transaction_id', 'paid_at', 'failed_at', 'failure_code', 'request_key',
    'order_url', 'preferred_payment_method', 'gateway_app_id', 'gateway_app_time', 'gateway_requested_at',
    'expires_at', 'reconciliation_required', 'reconciliation_resolved_at', 'reconciliation_note'], 175, 640)
d.e('D', 'DON_SHOP', 'shop_orders', ['*id*', 'status', 'subtotal', 'shipping_fee', 'discount_amount', 'discount_shop',
    'discount_platform', 'voucher_code', 'total_amount', 'confirmed_at', 'processing_at', 'shipping_at',
    'completed_at', 'cancelled_at', 'cancellation_reason', 'stock_restored_at'], 960, 600)
d.e('I', 'CHI_TIET_DON', 'order_items', ['*id*', 'product_name', 'product_image', 'product_price', 'quantity',
    'subtotal'], 1360, 600)
d.e('V', 'VOUCHER', 'vouchers', ['*id*', 'code', 'scope', 'type', 'value', 'min_order', 'max_discount',
    'usage_limit', 'per_user_limit', 'used_count', 'starts_at', 'ends_at', 'status'], 1000, 1010)
d.e('R', 'LUOT_DUNG_VOUCHER', 'voucher_redemptions', ['*id*', 'discount_amount', 'released_at', 'created_at'], 560, 1050)
d.r('co_gio', 'Có', 1300, 160)
d.l('U', 'co_gio', '0,1'); d.l('G', 'co_gio', '1,1', side=-1)
d.r('chua', 'Chứa', 1720, 470, ['quantity'])
d.l('G', 'chua', '0,n'); d.l('P', 'chua', '0,n', side=-1)
d.r('dat', 'Đặt', 560, 340)
d.l('U', 'dat', '0,n'); d.l('O', 'dat', '1,1')
d.r('doi_soat', 'Đối soát', 250, 300)
d.l('U', 'doi_soat', '0,n', role='admin', side=-1, dist=40); d.l('T', 'doi_soat', '0,1')
d.r('co_tt', 'Có', 375, 620)
d.l('O', 'co_tt', '0,n', side=-1); d.l('T', 'co_tt', '1,1')
d.r('tach', 'Tách thành', 770, 600)
d.l('O', 'tach', '1,n'); d.l('D', 'tach', '1,1', side=-1)
d.r('nhan', 'Nhận', 960, 370)
d.l('S', 'nhan', '0,n'); d.l('D', 'nhan', '1,1')
d.r('gom', 'Gồm', 1165, 600)
d.l('D', 'gom', '1,n'); d.l('I', 'gom', '1,1', side=-1)
d.r('duoc_mua', 'Được mua', 1545, 800)
d.l('P', 'duoc_mua', '0,n'); d.l('I', 'duoc_mua', '1,1', side=-1)
d.r('phat_hanh', 'Phát hành', 1225, 820)
d.l('S', 'phat_hanh', '0,n', via=[(1225, 260)]); d.l('V', 'phat_hanh', '0,1', side=-1)
d.r('duoc_dung', 'Được dùng', 780, 1050)
d.l('V', 'duoc_dung', '0,n'); d.l('R', 'duoc_dung', '1,1', side=-1)
d.r('ap_dung', 'Áp dụng cho', 560, 885)
d.l('O', 'ap_dung', '0,n'); d.l('R', 'ap_dung', '1,1')
d.r('ap_shop', 'Giảm cho', 790, 880)
d.l('D', 'ap_shop', '0,n', side=-1); d.l('R', 'ap_shop', '0,1', side=-1)
d.r('dung', 'Dùng', 320, 1120)
d.l('U', 'dung', '0,n', via=[(30, 160), (30, 1120)]); d.l('R', 'dung', '1,1', side=-1)
d.r('tao_vc', 'Tạo', 860, 1178)
d.l('U', 'tao_vc', '0,n', via=[(490, 245), (14, 245), (14, 1178)], side=-1)
d.l('V', 'tao_vc', '1,1')
d.note(24, 64, 'DON_HANG gom các DON_SHOP (mỗi gian hàng một đơn con). Tiền lưu DECIMAL; '
       'CHI_TIET_DON chụp lại tên, ảnh, giá tại thời điểm đặt.')
diagrams['2-don-hang-thanh-toan'] = d

# 3 -------------------------------------------------------------------------
d = Diagram('Sơ đồ 3 · Đấu giá, đánh giá sản phẩm và báo cáo vi phạm', 1720, 1165)
d.e('U', 'NGUOI_DUNG', 'users', USER_SHORT, 700, 150)
d.e('P', 'SAN_PHAM', 'products', PROD_SHORT, 230, 480)
d.e('A', 'PHIEN_DAU_GIA', 'auctions', ['*id*', 'start_price', 'current_price', 'bid_increment', 'starts_at',
    'ends_at', 'status', 'winner_checkout_expires_at', 'winner_checkout_reminded_at', 'settled_at'], 700, 480)
d.e('B', 'LUOT_TRA_GIA', 'bids', ['*id*', 'amount', 'created_at'], 1120, 480)
d.e('O', 'DON_HANG', 'orders', ['*id*', 'order_code', 'total_amount', '…'], 500, 800)
d.e('I', 'CHI_TIET_DON', 'order_items', ['*id*', 'product_name', 'product_price', 'quantity', '…'], 900, 800)
d.e('R', 'DANH_GIA', 'reviews', ['*id*', 'rating', 'comment', 'status', 'moderation_reason', 'created_at'], 900, 1060)
d.e('X', 'BAO_CAO', 'reports', ['*id*', 'target_type', 'target_id', 'target_name', 'reason', 'description',
    'status', 'handled_at', 'admin_note'], 1360, 820)
d.r('mo', 'Mở phiên', 560, 290)
d.l('U', 'mo', '0,n', side=-1); d.l('A', 'mo', '1,1', side=-1)
d.r('thang', 'Thắng', 840, 290)
d.l('U', 'thang', '0,n'); d.l('A', 'thang', '0,1')
d.r('dau_gia', 'Được đấu giá', 450, 480)
d.l('P', 'dau_gia', '0,n'); d.l('A', 'dau_gia', '1,1', side=-1)
d.r('nhan_bid', 'Nhận', 925, 420)
d.l('A', 'nhan_bid', '0,n'); d.l('B', 'nhan_bid', '1,1', side=-1)
d.r('gia_thang', 'Giá thắng', 925, 545)
d.l('A', 'gia_thang', '0,1', side=-1); d.l('B', 'gia_thang', '0,1')
d.r('tra_gia', 'Trả giá', 950, 300)
d.l('U', 'tra_gia', '0,n', dist=30); d.l('B', 'tra_gia', '1,1')
d.r('chot', 'Chốt thành', 590, 660)
d.l('A', 'chot', '0,1', side=-1); d.l('O', 'chot', '0,1', side=-1)
d.r('sinh', 'Sinh ra', 810, 660)
d.l('A', 'sinh', '0,1'); d.l('I', 'sinh', '0,1')
d.r('duoc_dg', 'Được đánh giá', 900, 930)
d.l('I', 'duoc_dg', '0,1'); d.l('R', 'duoc_dg', '1,1')
d.r('co_dg', 'Có', 430, 1060)
d.l('P', 'co_dg', '0,n', side=-1); d.l('R', 'co_dg', '1,1', side=-1)
d.r('viet', 'Viết', 1250, 1110)
d.l('U', 'viet', '0,n', via=[(700, 58), (1660, 58), (1660, 1110)]); d.l('R', 'viet', '1,1', side=-1)
d.r('gui', 'Gửi', 1200, 380)
d.l('U', 'gui', '0,n', side=-1, dist=95); d.l('X', 'gui', '0,1', side=-1)
d.r('xu_ly', 'Xử lý', 1480, 330)
d.l('U', 'xu_ly', '0,n', role='admin', dist=150); d.l('X', 'xu_ly', '0,1')
d.note(24, 1145, 'Mỗi SAN_PHAM chỉ có tối đa 1 phiên đang mở. BAO_CAO trỏ tới đối tượng theo target_type ∈ '
       '{PRODUCT, SHOP, VIDEO_COMMENT} + target_id (liên kết đa hình, không có khóa ngoại).')
diagrams['3-dau-gia-danh-gia'] = d

# 4 -------------------------------------------------------------------------
d = Diagram('Sơ đồ 4 · Nhắn tin, thông báo, hành vi người dùng và nhật ký', 1520, 1060)
d.e('U', 'NGUOI_DUNG', 'users', USER_SHORT, 700, 520)
d.e('C', 'CUOC_TRO_CHUYEN', 'conversations', ['*id*', 'context_key', 'last_message_at', 'created_at'], 190, 260)
d.e('M', 'TIN_NHAN', 'messages', ['*id*', 'body', 'created_at'], 190, 690)
d.e('P', 'SAN_PHAM', 'products', PROD_SHORT, 1250, 230)
d.e('V', 'LUOT_XEM', 'product_views', ['*id*', 'viewed_at'], 1250, 640)
d.e('N', 'THONG_BAO', 'notifications', ['*id*', 'type', 'dedup_key', 'data', 'read_at', 'created_at'], 330, 940)
d.e('L', 'LICH_SU_TIM_KIEM', 'search_logs', ['*id*', 'query', 'created_at'], 790, 940)
d.e('K', 'NHAT_KY', 'audit_logs', ['*id*', 'entity_type', 'entity_id', 'action', 'from_status', 'to_status',
    'reason', 'request_id', 'created_at'], 1250, 900)
d.r('ve', 'Hỏi về', 700, 110)
d.l('P', 've', '0,n', side=-1); d.l('C', 've', '0,1', side=-1)
d.r('mua', 'Là người mua', 450, 250)
d.l('U', 'mua', '0,n', dist=60); d.l('C', 'mua', '1,1', side=-1)
d.r('ban', 'Là người bán', 450, 345)
d.l('U', 'ban', '0,n', side=-1, dist=40); d.l('C', 'ban', '1,1')
d.r('tham_gia', 'Tham gia', 430, 470, ['last_read_message_id'])
d.l('U', 'tham_gia', '0,n'); d.l('C', 'tham_gia', '2,2', side=-1)
d.r('chua_tn', 'Chứa', 190, 470)
d.l('C', 'chua_tn', '0,n'); d.l('M', 'chua_tn', '1,1')
d.r('gui_tn', 'Gửi', 450, 690)
d.l('U', 'gui_tn', '0,n', side=-1, dist=60); d.l('M', 'gui_tn', '1,1', side=-1)
d.r('yeu_thich', 'Yêu thích', 960, 340, ['created_at'])
d.l('U', 'yeu_thich', '0,n'); d.l('P', 'yeu_thich', '0,n', side=-1)
d.r('xem', 'Xem', 990, 600)
d.l('U', 'xem', '0,n', side=-1); d.l('V', 'xem', '1,1')
d.r('duoc_xem', 'Được xem', 1250, 440)
d.l('P', 'duoc_xem', '0,n'); d.l('V', 'duoc_xem', '1,1')
d.r('nhan_tb', 'Nhận', 560, 800)
d.l('U', 'nhan_tb', '0,n', dist=30); d.l('N', 'nhan_tb', '1,1', side=-1)
d.r('tim', 'Tìm kiếm', 760, 790)
d.l('U', 'tim', '0,n', side=-1); d.l('L', 'tim', '1,1', side=-1)
d.r('thuc_hien', 'Thực hiện', 1010, 790)
d.l('U', 'thuc_hien', '0,n'); d.l('K', 'thuc_hien', '0,1')
d.note(24, 1040, 'THONG_BAO gắn người nhận qua notifiable_type/notifiable_id (hiện chỉ App\\Models\\User). '
       'NHAT_KY ghi entity_type/entity_id của đơn, thanh toán, sản phẩm, gian hàng, voucher, người dùng; '
       'actor rỗng = hệ thống.')
diagrams['4-tin-nhan-hanh-vi'] = d

for name, dg in diagrams.items():
    with open(f'{OUT}/{name}.svg', 'w', encoding='utf-8') as f:
        f.write(dg.svg())
    print(name, dg.w, dg.h)
