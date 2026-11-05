{{-- Gaya panel Setujui (tanda tangan) / Tolak: Approval Roster & Approval Pengajuan. --}}
<style>
  /* Tanda tangan approver (signature_pad). */
  .rm-ttd-kanvas { position: relative; height: 200px; border: 1.5px dashed #94A3B8; border-radius: 10px; background: #fff; touch-action: none; }
  .rm-ttd-kanvas canvas { position: absolute; inset: 0; width: 100%; height: 100%; border-radius: 10px; cursor: crosshair; }
  .rm-ttd-garis { position: absolute; left: 24px; right: 24px; bottom: 44px; border-bottom: 1px solid #CBD5E1; pointer-events: none; }
  .rm-ttd-petunjuk { position: absolute; left: 0; right: 0; bottom: 18px; text-align: center; font-size: 12px; color: #94A3B8; pointer-events: none; }
  .rm-ttd-pratinjau { max-height: 160px; max-width: 100%; border: 1px solid #E2E8F0; border-radius: 8px; padding: 6px; background: #fff; }
  .rm-ttd-hasil img { max-height: 110px; max-width: 280px; display: block; }
  /* Panel approval (Setujui / Tolak). */
  .rm-panel { display: flex; flex-direction: column; border: 1px solid #E2E8F0; border-radius: 12px; padding: 18px 20px; background: #fff; }
  .rm-panel__kepala { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; flex-wrap: wrap; }
  .rm-panel__ikon { width: 36px; height: 36px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
  .rm-panel__judul { font-weight: 700; font-size: 14.5px; color: #0F172A; line-height: 1.3; }
  .rm-panel__sub { font-size: 12px; color: #64748B; }
  .rm-panel__kaki { margin-top: auto; padding-top: 16px; display: flex; justify-content: flex-end; }
  .rm-label { display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px; }
  /* Tema memberi tinggi tetap pada .form-control — textarea dikembalikan ke tinggi isinya. */
  .rm-textarea { height: auto !important; min-height: 76px; resize: vertical; font-size: 13.5px; line-height: 1.45; padding: 10px 12px; }
  .rm-seg { display: inline-flex; background: #F1F5F9; border: 1px solid #E2E8F0; border-radius: 10px; padding: 3px; }
  .rm-seg__opsi { display: inline-flex; align-items: center; gap: 6px; border: 0; background: transparent; padding: 6px 12px; border-radius: 8px;
                  font-size: 12.5px; font-weight: 600; color: #64748B; cursor: pointer; white-space: nowrap; }
  .rm-seg__opsi.is-aktif { background: #fff; color: #1D4ED8; box-shadow: 0 1px 3px rgba(15, 23, 42, .12); }
  .rm-seg__opsi:focus-visible { outline: 2px solid #487fff; outline-offset: 1px; }
  .rm-aksi-teks { display: inline-flex; align-items: center; gap: 4px; border: 0; background: none; padding: 0; font-size: 12.5px; font-weight: 600; color: #475569; cursor: pointer; }
  .rm-aksi-teks:hover { text-decoration: underline; }
  @media (max-width: 575px) { .rm-panel__kaki .btn { width: 100%; } }
</style>
