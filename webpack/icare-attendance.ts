/**
 * iCare Attendance — frontend logic.
 *
 * Handles:
 *   - Member checklist counters
 *   - "Visiting member" live search + add
 *   - New visitor modal
 *   - Group photo upload/preview
 *   - Form submission (POST /api/icare/groups/{id}/meeting + optional photo upload)
 */

interface PersonResult {
  id: number;
  firstName: string;
  lastName: string;
  phone: string;
}

interface VisitorRecord {
  full_name: string;
  phone: string;
  instagram: string;
  address: string;
}

// ─── State ────────────────────────────────────────────────────────────────────

const visitingMembers: Map<number, PersonResult> = new Map();
const newVisitors: VisitorRecord[] = [];
let pendingPhotoBase64: string | null = null;

// ─── Utility ──────────────────────────────────────────────────────────────────

function esc(s: string): string {
  return s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
}

function apiUrl(path: string): string {
  return `${window.ICARE?.rootPath ?? ""}/api${path}`;
}

function setText(id: string, text: string): void {
  const el = document.getElementById(id);
  if (el) {
    el.textContent = text;
  }
}

// ─── Counter updates ──────────────────────────────────────────────────────────

function updateMemberCount(): void {
  const checked = document.querySelectorAll(".member-checkbox:checked").length;
  setText("memberCheckCount", String(checked));
}

function updateVisitingCount(): void {
  setText("visitingCheckCount", String(visitingMembers.size));
}

function updateVisitorCount(): void {
  setText("visitorCount", String(newVisitors.length));
}

// ─── Member checkboxes ────────────────────────────────────────────────────────

document.getElementById("memberList")?.addEventListener("change", updateMemberCount);

// ─── Visiting member search ───────────────────────────────────────────────────

let searchTimer: ReturnType<typeof setTimeout> | null = null;

const searchInput = document.getElementById("memberSearch") as HTMLInputElement | null;
const searchResults = document.getElementById("memberSearchResults");

searchInput?.addEventListener("input", () => {
  if (searchTimer) clearTimeout(searchTimer);
  const q = searchInput.value.trim();
  if (q.length < 2) {
    searchResults?.classList.add("d-none");
    if (searchResults) searchResults.innerHTML = "";
    return;
  }
  searchTimer = setTimeout(async () => {
    const resp = await fetch(apiUrl(`/icare/persons/search?q=${encodeURIComponent(q)}`));
    if (!resp.ok) return;
    const people: PersonResult[] = await resp.json();

    if (!searchResults) return;
    searchResults.innerHTML = "";
    if (people.length === 0) {
      searchResults.innerHTML = `<div class="list-group-item text-muted">No results</div>`;
    } else {
      for (const p of people) {
        if (visitingMembers.has(p.id)) continue;
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "list-group-item list-group-item-action";
        btn.innerHTML =
          `<span class="fw-semibold">${esc(`${p.firstName} ${p.lastName}`)}</span>` +
          (p.phone ? `<br><small class="text-muted">${esc(p.phone)}</small>` : "");
        btn.addEventListener("click", () => addVisitingMember(p));
        searchResults.appendChild(btn);
      }
    }
    searchResults.classList.remove("d-none");
  }, 300);
});

document.addEventListener("click", (e) => {
  if (!(e.target as Element).closest("#memberSearch, #memberSearchResults")) {
    searchResults?.classList.add("d-none");
  }
});

function addVisitingMember(person: PersonResult): void {
  visitingMembers.set(person.id, person);
  searchResults?.classList.add("d-none");
  if (searchInput) searchInput.value = "";
  renderVisitingList();
  updateVisitingCount();
}

function removeVisitingMember(id: number): void {
  visitingMembers.delete(id);
  renderVisitingList();
  updateVisitingCount();
}

function renderVisitingList(): void {
  const list = document.getElementById("visitingList");
  if (!list) return;
  list.innerHTML = "";
  visitingMembers.forEach((p) => {
    const item = document.createElement("div");
    item.className = "list-group-item d-flex align-items-center gap-3 py-3";
    item.innerHTML = `
      <i class="fa-solid fa-circle-check text-success"></i>
      <span class="flex-grow-1">
        <span class="fw-semibold">${esc(`${p.firstName} ${p.lastName}`)}</span>
        ${p.phone ? `<br><small class="text-muted">${esc(p.phone)}</small>` : ""}
      </span>
      <button type="button" class="btn btn-sm btn-ghost-danger" data-id="${p.id}">
        <i class="fa-solid fa-xmark"></i>
      </button>`;
    item.querySelector("button")?.addEventListener("click", () => removeVisitingMember(p.id));
    list.appendChild(item);
  });
}

// ─── New visitor modal ────────────────────────────────────────────────────────

document.getElementById("addVisitorBtn")?.addEventListener("click", () => {
  const nameEl = document.getElementById("visitorName") as HTMLInputElement | null;
  const phoneEl = document.getElementById("visitorPhone") as HTMLInputElement | null;
  const igEl = document.getElementById("visitorInstagram") as HTMLInputElement | null;
  const addrEl = document.getElementById("visitorAddress") as HTMLInputElement | null;
  if (nameEl) nameEl.value = "";
  if (phoneEl) phoneEl.value = "";
  if (igEl) igEl.value = "";
  if (addrEl) addrEl.value = "";
  // @ts-expect-error — Bootstrap 5 modal
  bootstrap.Modal.getOrCreateInstance(document.getElementById("newVisitorModal")).show();
});

document.getElementById("confirmAddVisitor")?.addEventListener("click", () => {
  const nameEl = document.getElementById("visitorName") as HTMLInputElement | null;
  if (!nameEl) return;
  const name = nameEl.value.trim();
  if (!name) {
    nameEl.classList.add("is-invalid");
    return;
  }
  nameEl.classList.remove("is-invalid");

  const phone = (document.getElementById("visitorPhone") as HTMLInputElement | null)?.value.trim() ?? "";
  const instagram = (document.getElementById("visitorInstagram") as HTMLInputElement | null)?.value.trim() ?? "";
  const address = (document.getElementById("visitorAddress") as HTMLInputElement | null)?.value.trim() ?? "";

  newVisitors.push({ full_name: name, phone, instagram, address });

  // @ts-expect-error — Bootstrap 5 modal
  bootstrap.Modal.getOrCreateInstance(document.getElementById("newVisitorModal")).hide();
  renderVisitorList();
  updateVisitorCount();
});

function renderVisitorList(): void {
  const list = document.getElementById("visitorList");
  if (!list) return;
  list.innerHTML = "";
  newVisitors.forEach((v, idx) => {
    const item = document.createElement("div");
    item.className = "list-group-item d-flex align-items-start gap-3 py-3";
    item.innerHTML = `
      <i class="fa-solid fa-user-tag text-warning mt-1"></i>
      <div class="flex-grow-1">
        <div class="fw-semibold">${esc(v.full_name)}</div>
        <div class="text-muted small d-flex flex-wrap gap-2 mt-1">
          ${v.phone ? `<span><i class="fa-solid fa-phone me-1"></i>${esc(v.phone)}</span>` : ""}
          ${v.instagram ? `<span><i class="fa-brands fa-instagram me-1"></i>${esc(v.instagram)}</span>` : ""}
          ${v.address ? `<span><i class="fa-solid fa-location-dot me-1"></i>${esc(v.address)}</span>` : ""}
        </div>
      </div>
      <button type="button" class="btn btn-sm btn-ghost-danger" data-idx="${idx}">
        <i class="fa-solid fa-xmark"></i>
      </button>`;
    item.querySelector("button")?.addEventListener("click", () => {
      newVisitors.splice(idx, 1);
      renderVisitorList();
      updateVisitorCount();
    });
    list.appendChild(item);
  });
}

// ─── Photo upload ─────────────────────────────────────────────────────────────

const photoInput = document.getElementById("photoInput") as HTMLInputElement | null;
const photoPreview = document.getElementById("photoPreview") as HTMLImageElement | null;
const photoWrap = document.getElementById("photoPreviewWrap");
const photoLabel = document.getElementById("photoLabel");

photoInput?.addEventListener("change", () => {
  const file = photoInput.files?.[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = (e) => {
    pendingPhotoBase64 = e.target?.result as string;
    if (photoPreview) photoPreview.src = pendingPhotoBase64;
    photoWrap?.classList.remove("d-none");
    photoLabel?.classList.add("d-none");
  };
  reader.readAsDataURL(file);
});

document.getElementById("removePhotoBtn")?.addEventListener("click", () => {
  pendingPhotoBase64 = null;
  if (photoInput) photoInput.value = "";
  if (photoPreview) photoPreview.src = "";
  photoWrap?.classList.add("d-none");
  photoLabel?.classList.remove("d-none");
});

// ─── Save attendance ──────────────────────────────────────────────────────────

document.getElementById("saveAttendanceBtn")?.addEventListener("click", async () => {
  const btn = document.getElementById("saveAttendanceBtn") as HTMLButtonElement | null;
  const statusEl = document.getElementById("saveStatus");

  const date = (document.getElementById("meetingDate") as HTMLInputElement | null)?.value ?? "";
  if (!date) {
    alert("Please select a meeting date.");
    return;
  }

  const memberIds = Array.from(document.querySelectorAll<HTMLInputElement>(".member-checkbox:checked"), (el) =>
    Number.parseInt(el.value, 10),
  );
  const allMemberIds = [...memberIds, ...Array.from(visitingMembers.keys())];

  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving…';
  }
  statusEl?.classList.add("d-none");

  try {
    const resp = await fetch(apiUrl(`/icare/groups/${window.ICARE?.groupId ?? 0}/meeting`), {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        meeting_date: date,
        location: (document.getElementById("meetingLocation") as HTMLInputElement | null)?.value.trim() ?? "",
        notes: (document.getElementById("meetingNotes") as HTMLTextAreaElement | null)?.value.trim() ?? "",
        member_ids: allMemberIds,
        visitors: newVisitors,
      }),
    });

    if (!resp.ok) {
      const err = await resp.json().catch(() => ({ error: "Unknown error" }));
      throw new Error((err as { error?: string }).error ?? "Failed to save");
    }

    const { meetingId } = (await resp.json()) as { meetingId: number };

    if (pendingPhotoBase64 && meetingId) {
      const photoResp = await fetch(apiUrl(`/icare/meeting/${meetingId}/photo`), {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ imgBase64: pendingPhotoBase64 }),
      });
      if (!photoResp.ok) {
        console.warn("Photo upload failed, but meeting was saved.");
      }
    }

    window.location.href = `${window.ICARE?.rootPath ?? ""}/v2/icare/groups/${window.ICARE?.groupId ?? 0}/history`;
  } catch (err) {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa-solid fa-circle-check me-2"></i>Save Attendance';
    }
    if (statusEl) {
      statusEl.className = "mt-2 text-center text-danger";
      statusEl.textContent = String(
        err instanceof Error ? err.message : "Failed to save attendance. Please try again.",
      );
      statusEl.classList.remove("d-none");
    }
  }
});
