#!/usr/bin/env python3
"""
Extract SQL from a REDCap install or upgrade HTML file.

The script reads an HTML file, finds SQL inside a <textarea>, and writes it to
a .sql output file. It is useful when REDCap displays installation or upgrade
SQL in the browser and the SQL needs to be saved for review or manual execution.

e.g. redcap_sql_html_path: "/tmp/redcap_sql/upgrade.html"

Usage:
    python3 extract-redcap-sql.py --input redcap_upgrade.html --output upgrade.sql

Optional:
    --textarea-index N          Select a specific textarea, starting from 0
    --drop-line-matching REGEX  Remove matching lines from the output
    --min-bytes N               Fail if extracted SQL is smaller than N bytes


Possibly redundant now REDCap install.php and upgrade.php have been updated
to provide a download link for the SQL, install.php?sql=1
"""


from __future__ import annotations

import argparse
import re
import sys
from html.parser import HTMLParser
from pathlib import Path


class TextareaParser(HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self._capturing = False
        self._current: list[str] = []
        self.textareas: list[str] = []

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        if tag.lower() == "textarea":
            self._capturing = True
            self._current = []

    def handle_endtag(self, tag: str) -> None:
        if tag.lower() == "textarea" and self._capturing:
            self.textareas.append("".join(self._current).strip())
            self._capturing = False
            self._current = []

    def handle_data(self, data: str) -> None:
        if self._capturing:
            self._current.append(data)


class HtmlSummaryParser(HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self._in_title = False
        self._skip_depth = 0
        self.title_parts: list[str] = []
        self.body_parts: list[str] = []

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        tag = tag.lower()
        if tag == "title":
            self._in_title = True
        elif tag in {"script", "style"}:
            self._skip_depth += 1

    def handle_endtag(self, tag: str) -> None:
        tag = tag.lower()
        if tag == "title":
            self._in_title = False
        elif tag in {"script", "style"} and self._skip_depth:
            self._skip_depth -= 1

    def handle_data(self, data: str) -> None:
        text = " ".join(data.split())
        if not text:
            return
        if self._in_title:
            self.title_parts.append(text)
        elif not self._skip_depth:
            self.body_parts.append(text)


def summarize_html(html: str, max_chars: int = 500) -> str:
    parser = HtmlSummaryParser()
    parser.feed(html)
    title = " ".join(parser.title_parts).strip()
    body = " ".join(parser.body_parts).strip()
    parts = []
    if title:
        parts.append(f"title={title!r}")
    if body:
        parts.append(f"excerpt={body[:max_chars]!r}")
    return "; ".join(parts)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--input", required=True, help="HTML input file")
    parser.add_argument("--output", required=True, help="SQL output file")
    parser.add_argument(
        "--textarea-index",
        type=int,
        default=None,
        help="Select a specific textarea by zero-based index",
    )
    parser.add_argument(
        "--drop-line-matching",
        action="append",
        default=[],
        help="Drop output lines matching this regular expression",
    )
    parser.add_argument(
        "--min-bytes",
        type=int,
        default=1,
        help="Fail when extracted SQL is smaller than this many bytes",
    )
    return parser.parse_args()


def looks_like_redcap_sql(text: str) -> bool:
    normalized = text.upper()
    return any(
        marker in normalized
        for marker in (
            "-- REDCAP INSTALLATION SQL",
            "CREATE TABLE",
            "INSERT INTO REDCAP_CONFIG",
            "UPDATE REDCAP_CONFIG",
            "REPLACE INTO REDCAP_HISTORY_VERSION",
        )
    )


def choose_sql(textareas: list[str], index: int | None, raw_content: str) -> str:
    if index is not None:
        try:
            return textareas[index]
        except IndexError as exc:
            raise ValueError(f"textarea index {index} not found") from exc

    for text in textareas:
        if text.strip():
            return text

    if looks_like_redcap_sql(raw_content):
        return raw_content.strip()

    raise ValueError("no non-empty textarea content found")


def filter_lines(sql: str, patterns: list[str]) -> str:
    if not patterns:
        return sql

    compiled = [re.compile(pattern) for pattern in patterns]
    lines = [
        line
        for line in sql.splitlines()
        if not any(pattern.search(line) for pattern in compiled)
    ]
    return "\n".join(lines).strip() + "\n"


def main() -> int:
    args = parse_args()
    input_path = Path(args.input)
    output_path = Path(args.output)

    html = input_path.read_text(encoding="utf-8", errors="replace")

    parser = TextareaParser()
    parser.feed(html)

    try:
        sql = choose_sql(parser.textareas, args.textarea_index, html)
        sql = filter_lines(sql, args.drop_line_matching)
    except ValueError as exc:
        summary = summarize_html(html)
        detail = f"; {summary}" if summary else ""
        print(f"extract-redcap-sql: {exc}{detail}", file=sys.stderr)
        return 1

    if len(sql.encode("utf-8")) < args.min_bytes:
        print(
            f"extract-redcap-sql: extracted SQL is smaller than {args.min_bytes} bytes",
            file=sys.stderr,
        )
        return 1

    output_path.parent.mkdir(parents=True, exist_ok=True)
    output_path.write_text(sql.rstrip() + "\n", encoding="utf-8")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
