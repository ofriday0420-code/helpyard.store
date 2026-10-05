from pathlib import Path

from reportlab.lib import colors
from reportlab.lib.pagesizes import landscape
from reportlab.lib.units import inch
from reportlab.pdfbase.pdfmetrics import stringWidth
from reportlab.pdfgen import canvas
from reportlab.platypus import Paragraph
from reportlab.lib.styles import ParagraphStyle


OUTPUT = Path("Helpyard_Project_Status_Report_First_Day_2026-10-05.pdf")
PAGE_W, PAGE_H = 960, 540
TOTAL = 15

NAVY = colors.HexColor("#102D2C")
GREEN = colors.HexColor("#1E6A5A")
GREEN_DARK = colors.HexColor("#174D43")
LIME = colors.HexColor("#DDEB72")
PAPER = colors.HexColor("#F4F7F2")
WHITE = colors.white
INK = colors.HexColor("#173A35")
MUTED = colors.HexColor("#61736D")
LINE = colors.HexColor("#D8E1D8")
AMBER = colors.HexColor("#F2C56B")
PALE_GREEN = colors.HexColor("#E5EFE8")
PALE_AMBER = colors.HexColor("#FBF0D9")
RED = colors.HexColor("#A64A3F")

PHASES = [
    ("Requirements & wireframes", 0, "Draft assets exist; owner approval is still required."),
    ("Engineering foundation", 80, "CI and local MariaDB integration evidence added; hosted run pending."),
    ("UI/UX design system", 45, "Responsive screens exist; full tokens, localization and accessibility remain."),
    ("Catalog & product engine", 60, "Five-section catalog, variants and audited inventory controls."),
    ("Accounts & dashboard", 50, "Authentication, addresses, orders, downloads and course learning."),
    ("Cart & checkout", 75, "Real reservation, snapshot, cart-clear and competing-checkout tests."),
    ("Payments (SSLCOMMERZ)", 65, "Risk/late/repeat and invalid validation data tested; sandbox pending."),
    ("Fulfillment", 60, "Physical shipment and protected digital/course delivery first slices."),
    ("Admin panel", 45, "Catalog/files/fulfillment plus new read-only order review console."),
    ("Search & SEO", 20, "Search/filter API and clean product URLs."),
    ("Security hardening", 55, "CSRF/ownership/session/payment/path controls; independent review pending."),
    ("QA & acceptance", 50, "Expanded MariaDB suite passes; full sandbox, browser and release matrix pending."),
    ("Production deployment", 0, "No staging/production deployment evidence."),
    ("PWA", 0, "Deferred until the web/API is stable."),
    ("Native mobile app", 0, "Deferred until API v1 and PWA outcomes."),
]

SCORE = sum(phase[1] for phase in PHASES)
FULL_PERCENT = SCORE / TOTAL
PREPROD_PERCENT = SCORE / 13


def rgb(c):
    return c.red, c.green, c.blue


def set_fill(pdf, color):
    pdf.setFillColor(color)


def rounded(pdf, x, y, w, h, fill, radius=12, stroke=None):
    pdf.setFillColor(fill)
    pdf.setStrokeColor(stroke or fill)
    pdf.roundRect(x, y, w, h, radius, fill=1, stroke=bool(stroke))


def text(pdf, x, y, value, size=12, color=INK, font="Helvetica", char_space=0):
    pdf.setFillColor(color)
    pdf.setFont(font, size)
    if char_space:
        t = pdf.beginText(x, y)
        t.setCharSpace(char_space)
        t.textLine(value)
        pdf.drawText(t)
        pdf._code.append("0 Tc")
    else:
        pdf.drawString(x, y, value)


def right_text(pdf, x, y, value, size=12, color=INK, font="Helvetica"):
    pdf.setFillColor(color)
    pdf.setFont(font, size)
    pdf.drawRightString(x, y, value)


def paragraph(pdf, x, top, width, value, size=11, color=INK, leading=None, font="Helvetica"):
    style = ParagraphStyle(
        "report",
        fontName=font,
        fontSize=size,
        leading=leading or size * 1.35,
        textColor=color,
        spaceAfter=0,
        splitLongWords=1,
    )
    p = Paragraph(value, style)
    _, height = p.wrap(width, PAGE_H)
    p.drawOn(pdf, x, top - height)
    return height


def rule(pdf, x1, y1, x2, y2, color=LINE, width=1):
    pdf.setStrokeColor(color)
    pdf.setLineWidth(width)
    pdf.line(x1, y1, x2, y2)


def header(pdf, page_no, section):
    pdf.setFillColor(PAPER)
    pdf.rect(0, 0, PAGE_W, PAGE_H, fill=1, stroke=0)
    text(pdf, 42, PAGE_H - 31, "HELPYARD.STORE  /  FIRST DAY STATUS BRIEF", 8, GREEN, "Helvetica-Bold", 0.8)
    right_text(pdf, PAGE_W - 42, PAGE_H - 31, section.upper(), 8, MUTED, "Helvetica-Bold")
    rule(pdf, 42, PAGE_H - 43, PAGE_W - 42, PAGE_H - 43)
    rule(pdf, 42, 32, PAGE_W - 42, 32)
    text(pdf, 42, 18, "05 OCT 2026  |  Engineering estimate against Implementation Plan v2.0", 7.5, MUTED)
    right_text(pdf, PAGE_W - 42, 18, f"{page_no:02d}  /  05", 8, GREEN, "Helvetica-Bold")


def title_block(pdf, title, subtitle, y=PAGE_H - 75):
    text(pdf, 42, y, title, 23, NAVY, "Helvetica-Bold")
    paragraph(pdf, 43, y - 12, PAGE_W - 86, subtitle, 10, MUTED, 13)


def ring(pdf, cx, cy, radius, percent, foreground=GREEN, background=colors.HexColor("#DCE5DC")):
    pdf.setLineWidth(11)
    pdf.setStrokeColor(background)
    pdf.circle(cx, cy, radius, fill=0, stroke=1)
    pdf.setStrokeColor(foreground)
    pdf.setLineCap(1)
    pdf.arc(cx - radius, cy - radius, cx + radius, cy + radius, 90, -360 * percent / 100)
    pdf.setLineCap(0)
    pdf.setFillColor(WHITE)
    pdf.circle(cx, cy, radius - 10, fill=1, stroke=0)
    pdf.setFillColor(NAVY)
    pdf.setFont("Helvetica-Bold", 32)
    pdf.drawCentredString(cx, cy + 1, f"{percent:.0f}%")
    pdf.setFillColor(MUTED)
    pdf.setFont("Helvetica-Bold", 8)
    pdf.drawCentredString(cx, cy - 18, "FULL ROADMAP")


def bullet(pdf, x, top, width, value, size=10, color=INK, dot=GREEN, gap=7):
    pdf.setFillColor(dot)
    pdf.circle(x + 3, top - 5, 2.2, fill=1, stroke=0)
    h = paragraph(pdf, x + 13, top, width - 13, value, size, color, size * 1.35)
    return h + gap


def bar(pdf, x, y, width, height, value, fill=GREEN, back=LINE):
    rounded(pdf, x, y, width, height, back, height / 2)
    if value > 0:
        rounded(pdf, x, y, max(height, width * value / 100), height, fill, height / 2)


def draw_page_one(pdf):
    pdf.setFillColor(NAVY)
    pdf.rect(0, 0, PAGE_W, PAGE_H, fill=1, stroke=0)
    pdf.setFillColor(GREEN_DARK)
    pdf.circle(790, 300, 245, fill=1, stroke=0)
    pdf.setFillColor(colors.HexColor("#215B4F"))
    pdf.circle(835, 358, 163, fill=1, stroke=0)
    text(pdf, 52, 485, "HELPYARD.STORE  /  PROJECT STATUS", 9, LIME, "Helvetica-Bold", 1.1)
    text(pdf, 52, 438, "FIRST DAY", 14, LIME, "Helvetica-Bold", 2.0)
    text(pdf, 52, 389, "Project Status", 37, WHITE, "Helvetica-Bold")
    text(pdf, 52, 346, "and Roadmap Briefing", 31, WHITE, "Helvetica-Bold")
    paragraph(pdf, 54, 311, 480, "A presentation-ready account of what we built together, how progress is estimated, and what remains before launch.", 14, colors.HexColor("#D7E5DD"), 21)
    text(pdf, 54, 231, "REPORT 04  |  AS OF 05 OCTOBER 2026, 08:25 (+06:00)", 9, LIME, "Helvetica-Bold")
    text(pdf, 54, 207, "Based on the Helpyard.store New Implementation Plan v2.0", 10, colors.HexColor("#D7E5DD"))
    ring(pdf, 735, 339, 104, FULL_PERCENT, LIME, colors.HexColor("#41685D"))
    pdf.setFillColor(WHITE)
    pdf.setFont("Helvetica-Bold", 24)
    pdf.drawCentredString(725, 202, f"{PREPROD_PERCENT:.1f}%")
    pdf.setFillColor(LIME)
    pdf.setFont("Helvetica-Bold", 7.5)
    pdf.drawCentredString(725, 183, "PRE-PRODUCTION PHASES 0-12")
    pdf.setFillColor(WHITE)
    pdf.setFont("Helvetica-Bold", 24)
    pdf.drawCentredString(864, 202, "+3 pp")
    pdf.setFillColor(LIME)
    pdf.setFont("Helvetica-Bold", 7.5)
    pdf.drawCentredString(864, 183, "SINCE REPORT 03")
    rounded(pdf, 52, 59, 856, 70, colors.HexColor("#173C37"), 13)
    text(pdf, 72, 101, "EXECUTIVE READOUT", 9, LIME, "Helvetica-Bold", 1)
    paragraph(pdf, 72, 87, 810, "Core storefront and commerce flows now have real local database evidence for stock contention and payment-review cases, and administrators can inspect orders in a new read-only console. <b>This is progress, not launch approval:</b> Gate 0, hosted CI, payment sandbox, and production acceptance remain open.", 10, WHITE, 14)
    text(pdf, 52, 31, "DIRECTIONAL ENGINEERING ESTIMATE  |  NOT A THIRD-PARTY AUDIT OR PRODUCTION ACCEPTANCE", 7.5, colors.HexColor("#B6C9C0"), "Helvetica-Bold")
    pdf.showPage()


def draw_page_two(pdf):
    header(pdf, 2, "What we did together")
    title_block(pdf, "The build journey, step by step", "Use this sequence as the narrative for your presentation: problem alignment → useful flows → operations → evidence → next gate.")
    x0, top = 52, 432
    steps = [
        ("01", "Read the v2 roadmap", "We grounded work in the 15-phase plan and identified the next unmet milestone rather than adding features at random."),
        ("02", "Deliver course learning", "Paid orders create access; customers can open lessons and record progress."),
        ("03", "Build admin operations", "Added trusted CLI admin provisioning, private file upload/revocation, and protected downloads."),
        ("04", "Manage catalog safely", "Added audited category/product management, inventory controls, and hidden-category safeguards."),
        ("05", "Prepare and prove Gate 0", "Created decision/wireframe drafts and MySQL CI integration coverage; ran all 14 migrations and seeders locally."),
        ("06", "Stabilize checkout & review", "Tested parallel stock contention and payment risk/late/rejection paths; added a read-only admin order-review console."),
    ]
    for index, (number, heading, detail) in enumerate(steps):
        y = top - index * 61
        pdf.setFillColor(GREEN if index < 5 else NAVY)
        pdf.circle(67, y - 4, 13, fill=1, stroke=0)
        pdf.setFillColor(WHITE)
        pdf.setFont("Helvetica-Bold", 8)
        pdf.drawCentredString(67, y - 7, number)
        if index < len(steps) - 1:
            rule(pdf, 67, y - 20, 67, y - 50, LINE, 2)
        text(pdf, 91, y + 2, heading, 11, NAVY, "Helvetica-Bold")
        paragraph(pdf, 91, y - 10, 515, detail, 8.8, MUTED, 11.2)

    rounded(pdf, 638, 48, 270, 372, WHITE, 13, LINE)
    text(pdf, 658, 376, "A SIMPLE PRESENTATION FLOW", 9, GREEN, "Helvetica-Bold")
    items = [
        ("1  START", "We followed the approved roadmap order."),
        ("2  SHOW", "Demonstrate storefront, customer access and admin workflows."),
        ("3  MEASURE", "Explain the estimate and what evidence changed it."),
        ("4  BE HONEST", "Name open approvals, sandbox and deployment gates."),
        ("5  ASK", "Request decisions and access needed for the next milestone."),
    ]
    yy = 346
    for heading, detail in items:
        text(pdf, 658, yy, heading, 9, NAVY, "Helvetica-Bold")
        h = paragraph(pdf, 658, yy - 8, 226, detail, 8.5, MUTED, 11)
        yy -= h + 26
    rounded(pdf, 658, 52, 230, 73, PALE_GREEN, 10)
    text(pdf, 672, 108, "SAMPLE 30-SECOND SUMMARY", 8, GREEN_DARK, "Helvetica-Bold")
    paragraph(pdf, 672, 96, 202, "We built the core storefront and operations flows, and local MariaDB now verifies stock contention and payment review. Progress is about 40%; owner sign-off, hosted CI and the payment sandbox remain.", 7.7, INK, 9.4)
    pdf.showPage()


def draw_phase_card(pdf, x, y, width, number, name, score, delta, evidence):
    rounded(pdf, x, y, width, 44, WHITE, 8, LINE)
    text(pdf, x + 9, y + 31, f"{number:02d}  {name}", 7.4, NAVY, "Helvetica-Bold")
    right_text(pdf, x + width - 9, y + 31, f"{score}%  {delta}", 7.4, GREEN_DARK, "Helvetica-Bold")
    bar(pdf, x + 9, y + 20, width - 18, 4, score, GREEN if score > 0 else LINE, LINE)
    paragraph(pdf, x + 9, y + 15, width - 18, evidence, 6.2, MUTED, 7.2)


def draw_page_three(pdf):
    header(pdf, 3, "Progress calculation")
    title_block(pdf, "How complete is the project?", "Evidence-weighted phase scores from v2.0. Scores are directional estimates, not task counts, effort spent, or a release-readiness score.")
    rounded(pdf, 52, 375, 856, 58, WHITE, 12, LINE)
    text(pdf, 72, 409, f"{SCORE} / {TOTAL * 100} phase-points = {FULL_PERCENT:.1f}%  →  {FULL_PERCENT:.0f}% FULL ROADMAP", 15, NAVY, "Helvetica-Bold")
    text(pdf, 72, 388, f"{SCORE} / {13 * 100} phase-points = {PREPROD_PERCENT:.1f}%  →  {PREPROD_PERCENT:.0f}% through pre-production phases 0-12", 10, GREEN, "Helvetica-Bold")

    left_delta = {1: "+10", 5: "+10", 6: "+5"}
    right_delta = {8: "+5", 11: "+15"}
    x_positions = [54, 346, 638]
    short_evidence = [
        "Draft flows exist; owner sign-off pending.",
        "CI + MariaDB proof; hosted run pending.",
        "Responsive basics; tokens, localization and accessibility remain.",
        "Catalog/API plus audited inventory controls.",
        "Authentication, addresses, orders, downloads and courses.",
        "Concurrent stock tests; coupons, shipping and guest-cart merge remain.",
        "Risk/late paths tested; provider sandbox pending.",
        "Shipping, protected files and course first flows.",
        "Read-only order review; mutations and operator tools remain.",
        "Search API; technical SEO not complete.",
        "Core controls; independent and operational review pending.",
        "MariaDB suite passes; full acceptance matrix pending.",
        "No staging or production deployment evidence.",
        "Deferred until web platform and API are stable.",
        "Deferred until API stability and PWA outcomes.",
    ]
    for idx, (name, score, _evidence) in enumerate(PHASES):
        row, col = divmod(idx, 3)
        y = 310 - row * 54
        draw_phase_card(
            pdf,
            x_positions[col],
            y,
            268,
            idx,
            name,
            score,
            left_delta.get(idx, right_delta.get(idx, "—")),
            short_evidence[idx],
        )
    rounded(pdf, 52, 39, 856, 42, PALE_AMBER, 9)
    text(pdf, 68, 65, "IMPORTANT GATE CHECK", 7.8, RED, "Helvetica-Bold")
    paragraph(pdf, 68, 55, 820, "The estimate exceeds the plan's approximate M0 percentage target, but <b>Gate 0 remains open</b>: owner approvals and hosted MySQL 8.4 CI are pending. Percentages never override acceptance criteria.", 7.5, INK, 9)
    pdf.showPage()


def draw_page_four(pdf):
    header(pdf, 4, "Next roadmap")
    title_block(pdf, "What happens next", "Keep the order risk-first. Move to new feature scope only after the active release gate is satisfied.")
    milestones = [
        ("NOW", "Close Gate 0", "Approve product and operating decisions; approve the sitemap/wireframes; get clean migration/integration evidence in hosted CI.", "GATE OPEN", AMBER),
        ("NEXT", "Complete S1 stabilization", "Run MySQL 8.4 CI, execute the SSLCOMMERZ sandbox matrix, finish payment-review procedures and fix defects.", "ACTIVE", GREEN),
        ("THEN", "Complete operations", "Add approved order/payment-review actions, course authoring, customer/content/support tools and operator workflows.", "PLANNED", GREEN),
        ("LATER", "Commerce & quality", "Design system, localization, shipping/coupons, customer lifecycle, licensing, SEO/accessibility, hardening and release QA.", "PLANNED", GREEN),
        ("RELEASE", "Stage, accept, launch", "Prove TLS/secrets, scheduler, monitoring, backup restore, rollback and production smoke checks.", "NOT STARTED", RED),
    ]
    start_y = 432
    for i, (tag, heading, detail, state, accent) in enumerate(milestones):
        y = start_y - i * 68
        rounded(pdf, 54, y - 49, 852, 57, WHITE, 10, LINE)
        rounded(pdf, 68, y - 32, 75, 22, accent, 8)
        text(pdf, 105.5, y - 24, tag, 7.5, WHITE if accent != AMBER else NAVY, "Helvetica-Bold")
        text(pdf, 158, y - 8, heading, 11, NAVY, "Helvetica-Bold")
        paragraph(pdf, 158, y - 20, 575, detail, 8.5, MUTED, 11)
        right_text(pdf, 883, y - 8, state, 7.5, accent if accent != AMBER else RED, "Helvetica-Bold")
    rounded(pdf, 54, 48, 852, 62, NAVY, 11)
    text(pdf, 72, 87, "NEXT DECISIONS / DEPENDENCIES", 8.5, LIME, "Helvetica-Bold")
    paragraph(pdf, 72, 74, 805, "Product/business owners: sign Gate 0 decisions and design. Technical owner: run/observe hosted CI and approve the payment sandbox test plan. Business/finance owner: define manual reconciliation, refunds and late-payment handling before any admin mutation workflow is built.", 8.8, WHITE, 12)
    pdf.showPage()


def draw_check_card(pdf, x, y, w, h, heading, items, accent=GREEN):
    rounded(pdf, x, y, w, h, WHITE, 11, LINE)
    pdf.setFillColor(accent)
    pdf.roundRect(x, y + h - 8, w, 8, 4, fill=1, stroke=0)
    text(pdf, x + 16, y + h - 29, heading, 9.5, NAVY, "Helvetica-Bold")
    yy = y + h - 45
    for item in items:
        used = bullet(pdf, x + 16, yy, w - 30, item, 7.7, INK, accent, 4)
        yy -= used


def draw_page_five(pdf):
    header(pdf, 5, "Future requirements")
    title_block(pdf, "The remaining work, grouped for clarity", "This is the full forward-looking checklist represented in the v2 roadmap and current implementation notes; items are not yet accepted as complete.")
    cols = [54, 346, 638]
    w, h = 268, 155
    rows_y = [267, 98]
    cards = [
        ("01  BUSINESS & DESIGN", [
            "Approve product scope, prices, delivery, roles, refunds and licensing policy.",
            "Approve sitemap, desktop/mobile flows and acceptance criteria.",
            "Define localization, accessibility and design-system direction.",
        ], GREEN),
        ("02  FOUNDATION & RELEASE", [
            "Hosted MySQL 8.4 CI, migrations and repeatability proof.",
            "Staging/production, TLS, secrets, scheduler, monitoring and rollback runbook.",
            "Backup/restore drill, load checks and production smoke acceptance.",
        ], NAVY),
        ("03  CUSTOMER LIFECYCLE", [
            "Email verification and password recovery.",
            "Wishlist, complete account dashboard and observable notifications.",
            "Support/customer operations tooling and policies.",
        ], GREEN),
        ("04  COMMERCE & PAYMENT", [
            "SSLCOMMERZ sandbox success, cancel, fail, repeat, late and risk scenarios.",
            "Approved reconciliation/refund workflow and admin payment-review resolution.",
            "Coupons, shipping zones/rates and guest-cart merge.",
        ], GREEN),
        ("05  DELIVERY & CONTENT", [
            "Signed/expiring downloads and usage limits; revocation policy.",
            "Course authoring/media, product images/content and software/site licensing.",
            "Delivery notifications with observable failure handling.",
        ], GREEN),
        ("06  QUALITY & GROWTH", [
            "SEO metadata, canonicals, sitemap, robots, structured data and internal links.",
            "Independent security review; accessibility, browser and performance QA.",
            "PWA/API v1 then native app only after web platform stability.",
        ], GREEN),
    ]
    for i, (heading, items, accent) in enumerate(cards):
        col = i % 3
        row = i // 3
        draw_check_card(pdf, cols[col], rows_y[row], w, h, heading, items, accent)
    text(pdf, 54, 67, "PRESENTATION CLOSE:", 8, GREEN_DARK, "Helvetica-Bold")
    paragraph(pdf, 160, 67, 746, "Ask for Gate 0 sign-off, hosted CI ownership, sandbox access, and the business decision-maker for payment reconciliation/refunds. These unlock the next safe build steps.", 8.3, MUTED, 10)
    pdf.showPage()


def main():
    pdf = canvas.Canvas(str(OUTPUT), pagesize=(PAGE_W, PAGE_H), pageCompression=1)
    pdf.setTitle("Helpyard.store First Day Project Status and Roadmap Briefing")
    pdf.setAuthor("Helpyard.store project team")
    pdf.setSubject("Implementation Plan v2.0 progress, evidence, future roadmap and open requirements")
    draw_page_one(pdf)
    draw_page_two(pdf)
    draw_page_three(pdf)
    draw_page_four(pdf)
    draw_page_five(pdf)
    pdf.save()
    print(f"Created {OUTPUT.resolve()} ({OUTPUT.stat().st_size} bytes)")
    print(f"Phase score {SCORE}/{TOTAL*100}; full {FULL_PERCENT:.2f}%; pre-production {PREPROD_PERCENT:.2f}%")


if __name__ == "__main__":
    main()
