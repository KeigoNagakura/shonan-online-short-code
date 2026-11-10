"""Check local Welcart sample PDFs using only Python's standard library."""

import re
import sys
import zlib
from pathlib import Path


def extract(path: Path) -> str:
    pdf = path.read_bytes()
    fragments = []
    for stream in re.finditer(rb"stream\r?\n", pdf):
        end = pdf.find(b"endstream", stream.end())
        if end < 0:
            continue
        try:
            data = zlib.decompress(pdf[stream.end() : end].rstrip(b"\r\n"))
        except zlib.error:
            continue
        if b" TJ" not in data:
            continue
        for match in re.finditer(rb"\[((?:\\.|[^]])*)\]\s*TJ", data, re.DOTALL):
            for value in re.finditer(rb"\(((?:\\.|[^)])*)\)", match.group(1), re.DOTALL):
                encoded = re.sub(rb"\\([()\\])", rb"\1", value.group(1))
                if len(encoded) % 2:
                    continue
                try:
                    fragments.append(encoded.decode("utf-16-be"))
                except UnicodeDecodeError:
                    continue
    return "\n".join(fragments)


for filename in sys.argv[1:]:
    path = Path(filename)
    raw = path.read_bytes()
    content = extract(path)
    title = "領収書" if "receipt" in path.name else "請求書"
    if "reduced" in path.name:
        expected = (title, "帳票テスト商品", "TEST-DOC-001", "8％対象", "8％消費税", "1,000", "80\n1,080")
        if title == "領収書":
            expected += ("¥1,080-",)
    else:
        expected = (title, "帳票テスト商品", "TEST-DOC-001", "消費税(10％)", "1,000", "100\n1,100")
        if title == "領収書":
            expected += ("¥1,100-",)
    missing = [term for term in expected if term not in content]
    if not raw.startswith(b"%PDF-") or b"%%EOF" not in raw[-32:] or missing:
        raise SystemExit(f"{path.name}: PDF or printed values invalid; missing={missing!r}")
    print(f"{path.name}: title, item, SKU, price, tax and total verified")
