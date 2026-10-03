"""Development-only snapshot QA. No production Python dependency or database."""

import hashlib
import json
import re
import sys
from pathlib import Path

sys.dont_write_bytecode = True
from qrcodegen import QrCode, QrSegment
from pypdf import PdfReader
from pypdf.generic import ContentStream
from PIL import Image, ImageDraw


def require(condition, message):
    if not condition:
        raise AssertionError(message)


def compact(value):
    return re.sub(r"\s+", "", value)


folder = Path(sys.argv[1]).resolve()
manifest = json.loads((folder / "manifest.json").read_text(encoding="utf-8"))
levels = [QrCode.Ecc.LOW, QrCode.Ecc.MEDIUM, QrCode.Ecc.QUARTILE, QrCode.Ecc.HIGH]
modes = {1: QrSegment.Mode.NUMERIC, 2: QrSegment.Mode.ALPHANUMERIC, 4: QrSegment.Mode.BYTE,
         8: QrSegment.Mode.KANJI, 7: QrSegment.Mode.ECI}
for case in manifest["qr"]:
    segments = ([QrSegment(modes[s["mode"]], s["count"], s["bits"]) for s in case["segments"]]
                if "segments" in case else QrSegment.make_segments(case["text"]))
    qr = QrCode.encode_segments(
        segments, levels[case["ecc"]],
        case["version"] or 1, case["version"] or 40, case["mask"], case["boost"],
    )
    matrix = "".join("1" if qr.get_module(x, y) else "0" for y in range(qr.get_size()) for x in range(qr.get_size()))
    require(hashlib.sha256(matrix.encode("ascii")).hexdigest() == case["sha256"], f"QR matrix mismatch: {case}")
    require(qr.get_version() == case["actual_version"] and qr.get_mask() == case["actual_mask"]
            and qr.get_error_correction_level().ordinal == case["actual_ecc"], "QR configuration mismatch")
print(f"Official Nayuki v1.8.0 parity OK: {len(manifest['qr'])} matrices (versions 1-40, ECC 0-3, masks 0-7).")

decoded = False
try:
    import zxingcpp
except ImportError:
    print("QR decoding SKIPPED: optional zxing-cpp not installed (module matrices still verified).")
else:
    with Image.open(folder / "payment.png") as png:
        code = zxingcpp.read_barcode(png)
        require(code is not None and code.text == (folder / "payment.spayd").read_text(encoding="ascii"), "PNG QR decode mismatch")
        decoded = True
        print("PNG QR decoded exactly by independent ZXing-C++ decoder.")

page_counts = {}
for fixture in manifest["fixtures"]:
    name = fixture["name"]
    reader = PdfReader(folder / f"{name}.pdf", strict=True)
    page_counts[name] = len(reader.pages)
    catalog = reader.trailer["/Root"]
    require(catalog["/Lang"] == "cs-CZ" and catalog["/MarkInfo"]["/Marked"], f"PDF language/tags: {name}")
    root = catalog["/StructTreeRoot"]
    document = root["/K"][0].get_object()
    require(document["/S"] == "/Document", "Missing Document tag")
    elements = [kid.get_object() for kid in document["/K"]]
    require(elements[0]["/S"] == "/H1", "Reading order must start with H1")
    require(sum(e["/S"] == "/H1" for e in elements) == 1, "Expected exactly one H1")
    require(all(e["/S"] in ["/H1", "/H2", "/H3", "/P", "/Figure"] for e in elements), "Unexpected semantic role")
    figures = [e for e in elements if e["/S"] == "/Figure"]
    require(len(figures) == int(fixture["payable"]), f"Unexpected QR Figure: {name}")
    require(all(e["/Alt"] == "qr kód k platbě" for e in figures), "Exact Figure alt missing")
    parents = root["/ParentTree"]["/Nums"]
    parent_map = {int(parents[i]): parents[i + 1] for i in range(0, len(parents), 2)}
    reading_order = []
    for e in elements:
        for kid in e["/K"]:
            page_ref = kid.raw_get("/Pg")
            page_number = next(i for i, page in enumerate(reader.pages) if page.indirect_reference == page_ref)
            reading_order.append((page_number, int(kid["/MCID"])))
            require(parent_map[page_number][int(kid["/MCID"])].get_object() == e, "ParentTree/MCR mismatch")
    require(reading_order == sorted(reading_order), f"Incorrect structure reading order: {name}")
    all_text = "\n".join(page.extract_text() for page in reader.pages)
    all_text = re.sub(r"Strana\s+\d+\s*/\s*\d+", "", all_text)
    normalized = compact(all_text)
    cursor = 0
    # Every snapshot paragraph and item line must survive PDF encoding and pagination.
    for line in (folder / f"{name}.txt").read_text(encoding="utf-8").splitlines():
        if not line.strip() or line.startswith("QR platba (SPAYD):"):
            continue
        needle = compact(line)
        position = normalized.find(needle, cursor)
        require(position >= 0, f"Lost/misordered PDF text in {name}: {line[:120]}")
        cursor = position + len(needle)
    require("ŽanetaŘíhová" in normalized and "Přílišžluťoučkýkůň" in normalized, "Czech glyph extraction failed")
    for page_number, page in enumerate(reader.pages):
        font = page["/Resources"]["/Font"]["/F1"]
        require(font["/Subtype"] == "/Type0" and font["/ToUnicode"].get_data(), "Missing Unicode mapping")
        descendant = font["/DescendantFonts"][0].get_object()
        require(descendant["/FontDescriptor"]["/FontFile2"].get_data().startswith(b"\x00\x01\x00\x00"), "Font not embedded")
        widths = descendant["/W"][1]
        size = 10.0
        x = y = 0.0
        marked = False
        artifact = False
        mcids = []
        for operands, operator in ContentStream(page["/Contents"], reader).operations:
            if operator == b"BDC":
                artifact = operands[0] == "/Artifact"
                marked = True
                if not artifact:
                    mcids.append(int(operands[1]["/MCID"]))
            elif operator == b"EMC":
                marked = artifact = False
            elif operator == b"Tf":
                size = float(operands[1])
            elif operator == b"Tm":
                x, y = float(operands[4]), float(operands[5])
            elif operator == b"Tj":
                require(marked, "Untagged text")
                raw = operands[0].original_bytes if hasattr(operands[0], "original_bytes") else bytes(operands[0])
                cids = [int.from_bytes(raw[i:i + 2], "big") for i in range(0, len(raw), 2)]
                width = sum(float(widths[cid - 1]) for cid in cids) * size / 1000
                require(53.9 <= x and x + width <= 541.1, f"Horizontal clipping: {name} page {page_number + 1}")
                require((29.9 <= y <= 30.1) if artifact else (58 <= y <= 788.1), "Vertical clipping")
            elif operator == b"Do":
                require(marked and not artifact, "QR must be tagged")
                image = page["/Resources"]["/XObject"][operands[0]]
                if decoded:
                    png = Image.frombytes("L", (int(image["/Width"]), int(image["/Height"])), image.get_data()).resize((600, 600), Image.Resampling.NEAREST)
                    code = zxingcpp.read_barcode(png)
                    snapshot = json.loads((folder / f"{name}.json").read_text(encoding="utf-8"))
                    require(code is not None and f"X-VS:{snapshot['order']['order_number']}" in code.text, "Embedded PDF QR decoding failed")
        require(mcids == list(range(len(parent_map[page_number]))), "MCIDs not sequential/complete")
    if name == "multipage":
        require(len(reader.pages) >= 6 and "Položka-048" in all_text and "KONEC-PODMÍNEK" in all_text, "Multipage truncation")
    (folder / f"{name}.extracted.txt").write_text(all_text, encoding="utf-8")
    print(f"PDF QA OK: {name}, {len(reader.pages)} pages; full Unicode text, embedded font, tags, reading order, bounds.")

if "--contact-sheets" in sys.argv:
    rendered = [image for fixture in manifest["fixtures"] for image in sorted(folder.glob(f"{fixture['name']}-*.png"))
                if int(image.stem.rsplit("-", 1)[1]) <= page_counts[fixture["name"]]]
    require(rendered, "Render PDF pages with Poppler before creating contact sheets")
    for start in range(0, len(rendered), 15):
        sheet = Image.new("RGB", (840, 2050), "#d9dde2")
        draw = ImageDraw.Draw(sheet)
        for index, image_path in enumerate(rendered[start:start + 15]):
            image = Image.open(image_path).convert("RGB")
            image.thumbnail((270, 382))
            x, y = (index % 3) * 280 + 5, (index // 3) * 410 + 22
            sheet.paste(image, (x, y))
            draw.text((x, y - 17), image_path.name, fill="black")
        sheet.save(folder / f"contact-sheet-{start // 15 + 1}.png")
    print(f"Contact sheets generated from {len(rendered)} Poppler page images.")

print("No PDF/UA certification is claimed. Validate assistive-technology behavior manually.")
