#!/usr/bin/env python3
"""Compare all six additional cmap formats against FreeType's independent public API.

Requires the pinned fontTools developer dependency and the platform FreeType library
(e.g. libfreetype6 on Ubuntu). FreeType performs mapping only: this does not certify
all malformed inputs, font licensing, script shaping or rasterization.
"""
from __future__ import annotations

import argparse
import base64
import ctypes
import ctypes.util
import hashlib
import io
import json
from pathlib import Path
import subprocess

import fontTools
from fontTools.ttLib import TTFont
from fontTools.ttLib.tables.DefaultTable import DefaultTable


class FreeType:
    def __init__(self) -> None:
        name = ctypes.util.find_library("freetype")
        if name is None:
            raise RuntimeError("The independent FreeType shared library is required")
        self.api = ctypes.CDLL(name)
        self.api.FT_Init_FreeType.argtypes = [ctypes.POINTER(ctypes.c_void_p)]
        self.api.FT_New_Memory_Face.argtypes = [ctypes.c_void_p, ctypes.c_void_p,
                                             ctypes.c_long, ctypes.c_long, ctypes.POINTER(ctypes.c_void_p)]
        self.api.FT_Select_Charmap.argtypes = [ctypes.c_void_p, ctypes.c_uint]
        self.api.FT_Get_Char_Index.argtypes = [ctypes.c_void_p, ctypes.c_ulong]
        self.api.FT_Get_Char_Index.restype = ctypes.c_uint
        self.api.FT_Done_Face.argtypes = [ctypes.c_void_p]
        self.api.FT_Done_FreeType.argtypes = [ctypes.c_void_p]
        self.api.FT_Library_Version.argtypes = [ctypes.c_void_p] + [ctypes.POINTER(ctypes.c_int)] * 3
        self.library = ctypes.c_void_p()
        self.check(self.api.FT_Init_FreeType(ctypes.byref(self.library)))
        major, minor, patch = ctypes.c_int(), ctypes.c_int(), ctypes.c_int()
        self.api.FT_Library_Version(self.library, ctypes.byref(major), ctypes.byref(minor), ctypes.byref(patch))
        self.version = f"{major.value}.{minor.value}.{patch.value}"

    def check(self, error: int) -> None:
        if error:
            raise RuntimeError(f"FreeType returned error {error}")

    def map(self, data: bytes, points: list[int]) -> list[int]:
        buffer = ctypes.create_string_buffer(data)
        face = ctypes.c_void_p()
        self.check(self.api.FT_New_Memory_Face(self.library, buffer, len(data), 0, ctypes.byref(face)))
        try:
            self.check(self.api.FT_Select_Charmap(face, 0x756E6963))
            return [self.api.FT_Get_Char_Index(face, code) for code in points]
        finally:
            self.api.FT_Done_Face(face)

    def close(self) -> None:
        self.api.FT_Done_FreeType(self.library)


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--php", required=True)
    args = parser.parse_args()
    root = Path(__file__).resolve().parents[1]
    process = subprocess.run([args.php, str(root / "tools/inspect_additional_cmaps.php")],
                             check=True, capture_output=True, text=True)
    cases = json.loads(process.stdout)
    results = []
    oracle = FreeType()
    try:
        for case in cases:
            font = TTFont(root / "tests/fixtures/ToolkitUnicode.ttf", recalcBBoxes=False, recalcTimestamp=False)
            table = DefaultTable("cmap")
            table.data = base64.b64decode(case["cmap"], validate=True)
            font["cmap"] = table
            stream = io.BytesIO()
            font.save(stream)
            font.close()
            data = stream.getvalue()
            points = [probe["codepoint"] for probe in case["probes"]]
            expected = oracle.map(data, points)
            actual = [probe["glyph"] for probe in case["probes"]]
            if expected != actual:
                raise ValueError(f"Format {case['format']}: FreeType {expected} != PHP {actual}")
            results.append({"format": case["format"], "probes": len(points),
                            "fontSha256": hashlib.sha256(data).hexdigest(),
                            "cmapSha256": hashlib.sha256(table.data).hexdigest(),
                            "values": case["probes"]})
    finally:
        oracle.close()
    print(json.dumps({"status": "passed", "oracle": "FreeType", "version": oracle.version,
                      "fontTools": fontTools.__version__, "formats": results}, indent=2))


if __name__ == "__main__":
    main()
