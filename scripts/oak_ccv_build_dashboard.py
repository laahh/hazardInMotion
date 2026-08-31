#!/usr/bin/env python3
"""Build compact OAK CCV dashboard JSON from Tidak_Sesuai_OAK source (OBSERVASI AREA KRITIS only)."""

from __future__ import annotations

import collections
import json
import os
import re
from collections import defaultdict
from datetime import datetime, timedelta

SRC = os.path.join(os.path.dirname(__file__), "..", "Tidak_Sesuai_OAK_YTD_2026_2_Sheets.json")
OUT = os.path.join(os.path.dirname(__file__), "..", "resources", "data", "oak_ccv_dashboard.json")
JENIS = "OBSERVASI AREA KRITIS"
ENTITY_ORDER = ["BC", "BCE", "Unggul", "Primac", "Suprima", "Yayasan", "Mitra"]
PATTERNS = [
    (re.compile(r"yayasan", re.I), "Yayasan"),
    (re.compile(r"berau\s+coal\s+energy", re.I), "BCE"),
    (re.compile(r"berau\s+coal", re.I), "BC"),
    (re.compile(r"unggul", re.I), "Unggul"),
    (re.compile(r"primac", re.I), "Primac"),
    (re.compile(r"suprima", re.I), "Suprima"),
]


def classify(name: str | None) -> tuple[str, str]:
    n = name or ""
    for pat, lab in PATTERNS:
        if pat.search(n):
            return "BC", lab
    return "Mitra", "Mitra"


def iso_week(dt: datetime) -> str:
    y, w, _ = dt.isocalendar()
    return f"{y}-W{w:02d}"


def week_range_label(week_key: str) -> str:
    y_s, w_s = week_key.split("-W")
    monday = datetime.strptime(f"{int(y_s)}-W{int(w_s):02d}-1", "%G-W%V-%u")
    sunday = monday + timedelta(days=6)
    months = [None, "Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"]

    def fmt(d: datetime) -> str:
        return f"{d.day} {months[d.month]}"

    return f"W{int(w_s)} ({fmt(monday)}–{fmt(sunday)})"


def norm(value: object) -> str:
    s = str(value).strip() if value is not None else ""
    return s if s else "(Tidak diisi)"


def main() -> None:
    src = os.path.abspath(SRC)
    out = os.path.abspath(OUT)
    os.makedirs(os.path.dirname(out), exist_ok=True)

    print("loading", src)
    with open(src, encoding="utf-8") as f:
        data = json.load(f)

    pel = data["sheets"]["Pelaksanaan CCV"]["records"]
    stop = data["sheets"]["Stop Aktivitas pelaksanaan CCV"]["records"]
    oak = [r for r in pel if (r.get("jenis data") or "").strip().upper() == JENIS]
    print("oak rows", len(oak))

    oak_tasks: set = set()
    oak_by_task: dict = {}
    cube_tasks: dict = defaultdict(set)
    cube_rows: collections.Counter = collections.Counter()
    tools_c: collections.Counter = collections.Counter()
    layer_c: collections.Counter = collections.Counter()
    mitra_c: collections.Counter = collections.Counter()
    mitra_site: collections.Counter = collections.Counter()
    dates: list[datetime] = []
    geotag: collections.Counter = collections.Counter()
    aktivitas_set: set[str] = set()
    daily_rows: collections.Counter = collections.Counter()

    for r in oak:
        dt = datetime.fromisoformat(r["date all"][:19])
        dates.append(dt)
        week = iso_week(dt)
        site = norm(r.get("site"))
        _group, entity = classify(r.get("perusahaan pelapor all karyawan"))
        akt = norm(r.get("aktivitas pekerjaan OAK"))
        aktivitas_set.add(akt)
        tool = norm(r.get("tools pengawasan"))
        layer = norm(r.get("Layer Pelapor"))
        task = r.get("task")
        oak_tasks.add(task)
        if task not in oak_by_task:
            oak_by_task[task] = r
        key = (week, site, entity, akt)
        cube_rows[key] += 1
        cube_tasks[key].add(task)
        tools_c[(site, entity, tool)] += 1
        layer_c[(site, entity, layer)] += 1
        geotag[r.get("Geotagging") or "(Tidak diisi)"] += 1
        daily_rows[(dt.strftime("%Y-%m-%d"), week, site, entity)] += 1
        if entity == "Mitra":
            co = norm(r.get("perusahaan pelapor all karyawan"))
            mitra_c[co] += 1
            mitra_site[(site, co)] += 1

    oak_cube = []
    for key, n in cube_rows.items():
        week, site, entity, akt = key
        oak_cube.append(
            {
                "week": week,
                "site": site,
                "entity": entity,
                "group": "BC" if entity != "Mitra" else "Mitra",
                "aktivitas": akt,
                "rows": n,
                "tasks": len(cube_tasks[key]),
            }
        )

    daily_cube = []
    for key, n in daily_rows.items():
        day, week, site, entity = key
        daily_cube.append(
            {
                "date": day,
                "week": week,
                "site": site,
                "entity": entity,
                "group": "BC" if entity != "Mitra" else "Mitra",
                "rows": n,
            }
        )

    tools = [{"site": s, "entity": e, "tool": t, "rows": n} for (s, e, t), n in tools_c.items()]
    layers = [{"site": s, "entity": e, "layer": layer, "rows": n} for (s, e, layer), n in layer_c.items()]
    top_mitra = [{"company": k, "rows": v} for k, v in mitra_c.most_common(20)]
    mitra_by_site = [{"site": s, "company": c, "rows": n} for (s, c), n in mitra_site.items()]

    stop_out = []
    stop_weeks: collections.Counter = collections.Counter()
    stop_tasks: set = set()
    matched_oak_tasks: set = set()
    for r in stop:
        dt = datetime.fromisoformat(r["Second of Tanggal"][:19])
        week = iso_week(dt)
        stop_weeks[week] += 1
        task = r.get("task")
        stop_tasks.add(task)
        matched = task in oak_tasks
        if matched:
            matched_oak_tasks.add(task)
        oak_row = oak_by_task.get(task)
        akt = (r.get("Aktivitas Name") or "").strip()
        stop_out.append(
            {
                "task": task,
                "tanggal": dt.strftime("%Y-%m-%d %H:%M"),
                "week": week,
                "aktivitas": akt,
                "sub_aktivitas": (r.get("Sub Aktivitas Name") or "").strip(),
                "object": (r.get("Object") or "").strip(),
                "detil_object": (r.get("Detil Object") or "").strip(),
                "jawaban": (r.get("Jawaban") or "").strip(),
                "matched_oak": matched,
                "aktivitas_in_oak": akt in aktivitas_set,
                "oak_site": oak_row.get("site") if oak_row else None,
                "oak_perusahaan": oak_row.get("perusahaan pelapor all karyawan") if oak_row else None,
                "oak_entity": classify(oak_row.get("perusahaan pelapor all karyawan"))[1] if oak_row else None,
            }
        )

    weeks_oak = sorted({row["week"] for row in oak_cube})
    week_meta = []
    for w in weeks_oak:
        rows_w = sum(row["rows"] for row in oak_cube if row["week"] == w)
        week_meta.append({"week": w, "label": week_range_label(w), "rows": rows_w})

    payload = {
        "schema_version": "1.0",
        "jenis_data": JENIS,
        "source_file": data.get("source_file"),
        "generated_at_utc": datetime.utcnow().strftime("%Y-%m-%dT%H:%M:%SZ"),
        "meta": {
            "pelaksanaan_rows": len(oak),
            "pelaksanaan_tasks": len(oak_tasks),
            "date_min": min(dates).strftime("%Y-%m-%d"),
            "date_max": max(dates).strftime("%Y-%m-%d"),
            "days": (max(dates).date() - min(dates).date()).days + 1,
            "stop_rows": len(stop_out),
            "stop_tasks": len(stop_tasks),
            "stop_matched_oak_tasks": len(matched_oak_tasks),
            "stop_date_min": min(s["tanggal"][:10] for s in stop_out),
            "stop_date_max": max(s["tanggal"][:10] for s in stop_out),
            "geotagging": dict(geotag),
            "entity_order": ENTITY_ORDER,
            "bc_entities": ENTITY_ORDER[:-1],
        },
        "weeks": week_meta,
        "sites": sorted({row["site"] for row in oak_cube}),
        "oak_cube": oak_cube,
        "daily_cube": daily_cube,
        "tools": tools,
        "layers": layers,
        "top_mitra": top_mitra,
        "mitra_by_site": mitra_by_site,
        "stop_rows": stop_out,
        "stop_weeks": [
            {"week": w, "label": week_range_label(w), "rows": n} for w, n in sorted(stop_weeks.items())
        ],
    }

    with open(out, "w", encoding="utf-8") as f:
        json.dump(payload, f, ensure_ascii=False, separators=(",", ":"))

    print(
        "cube",
        len(oak_cube),
        "daily",
        len(daily_cube),
        "tools",
        len(tools),
        "layers",
        len(layers),
        "stop",
        len(stop_out),
    )
    print("rows", len(oak), "tasks", len(oak_tasks), "matched stop tasks", len(matched_oak_tasks))
    print("wrote", out, "bytes", os.path.getsize(out))


if __name__ == "__main__":
    main()
