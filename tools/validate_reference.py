"""Verify that the supplied Adobe usage guide retains its original bytes."""

from pathlib import Path
import hashlib
import json


def main() -> None:
    root = Path(__file__).resolve().parents[1]
    metadata = json.loads((root / "resources/reference-documents/metadata.json").read_text(encoding="utf-8"))
    reference, = metadata["documents"]
    data = (root / reference["path"]).read_bytes()
    if (len(data) != reference["bytes"]
            or hashlib.sha256(data).hexdigest() != reference["sha256"]
            or reference["filename"] != "OTGuide.pdf"
            or reference["physicalPdfPages"] != 13):
        raise ValueError("The supplied Adobe guide's original identity has changed")
    print(json.dumps({"status": "passed", "filename": reference["filename"],
                      "bytes": len(data), "sha256": reference["sha256"]}))


if __name__ == "__main__":
    main()
