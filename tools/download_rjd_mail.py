#!/usr/bin/env python3
"""Одноразовое скачивание XLSX-вложений РЖД из Microsoft Exchange."""

from __future__ import annotations

import re
import sys
from datetime import date
from pathlib import Path

try:
    from exchangelib import Account, Configuration, Credentials, DELEGATE, EWSDateTime, FileAttachment, NTLM
except ImportError:
    print("Не установлен модуль exchangelib.", file=sys.stderr)
    print("Установите: python3 -m pip install --user exchangelib", file=sys.stderr)
    raise SystemExit(2)


EXCHANGE_SERVER = "post.metafrax.ru"
EXCHANGE_EMAIL = "info.portal@metafrax.ru"
EXCHANGE_LOGIN = "info.portal"
EXCHANGE_PASSWORD = "97h^eV4rPSk#n38"
MAIL_SENDER = "cargolk@gvc.rzd.ru"
OUTPUT_DIRECTORY = "tmp/mf/rjd"


def two_months_ago(today: date) -> date:
    month = today.month - 2
    year = today.year
    while month < 1:
        month += 12
        year -= 1
    days = [31, 29 if year % 4 == 0 and (year % 100 != 0 or year % 400 == 0) else 28,
            31, 30, 31, 30, 31, 31, 30, 31, 30, 31]
    return date(year, month, min(today.day, days[month - 1]))


def safe_name(value: str, message_id: str) -> str:
    name = Path(value.replace("\\", "/")).name
    name = re.sub(r"[^0-9A-Za-zА-Яа-яЁё._() -]+", "_", name).strip(" .")
    return name or f"rjd_{message_id}.xlsx"


def save_attachment(folder: Path, name: str, message_id: str, content: bytes) -> tuple[Path, bool]:
    target = folder / safe_name(name, message_id)
    if target.exists():
        if target.read_bytes() == content:
            return target, False
        target = folder / f"{target.stem}_{message_id}{target.suffix}"
    target.write_bytes(content)
    return target, True


def main() -> int:
    output = Path(OUTPUT_DIRECTORY).resolve()
    output.mkdir(parents=True, exist_ok=True)
    since = two_months_ago(date.today())

    credentials = Credentials(username=EXCHANGE_LOGIN, password=EXCHANGE_PASSWORD)
    config = Configuration(
        server=EXCHANGE_SERVER,
        credentials=credentials,
        auth_type=NTLM,
    )
    account = Account(
        primary_smtp_address=EXCHANGE_EMAIL,
        config=config,
        autodiscover=False,
        access_type=DELEGATE,
    )

    messages = account.inbox.filter(
        datetime_received__gte=EWSDateTime(
            since.year, since.month, since.day, tzinfo=account.default_timezone
        )
    ).only(
        "sender", "datetime_received", "subject", "attachments", "message_id"
    ).order_by("datetime_received")

    checked = 0
    matched = 0
    downloaded = 0
    existing = 0
    for message in messages:
        checked += 1
        sender = str(getattr(message.sender, "email_address", "") or "").lower()
        if sender != MAIL_SENDER.lower():
            continue

        xlsx = [
            attachment for attachment in message.attachments
            if isinstance(attachment, FileAttachment)
            and Path(attachment.name or "").suffix.lower() == ".xlsx"
        ]
        if not xlsx:
            continue

        matched += 1
        message_id = re.sub(r"\W+", "", str(message.message_id or matched))[-16:] or str(matched)
        for attachment in xlsx:
            target, created = save_attachment(
                output,
                attachment.name or "",
                message_id,
                attachment.content,
            )
            if created:
                downloaded += 1
                print(f"Скачан: {target}")
            else:
                existing += 1

    print(
        f"Готово: проверено писем — {checked}, подходящих — {matched}, "
        f"скачано XLSX — {downloaded}, уже существовало — {existing}."
    )
    print(f"Каталог: {output}")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as error:
        print(f"Ошибка Exchange: {error}", file=sys.stderr)
        raise SystemExit(1)
