// ==========================================
// AUREUS - Módulo UI (js/ui.js)
// ==========================================

export function hideAllViews(detailTimer) {
  document.getElementById("view-catalog").style.display = "none";
  document.getElementById("view-detail").style.display = "none";
  document.getElementById("view-workshop").style.display = "none";
  document.getElementById("view-vault").style.display = "none";
  document.getElementById("view-admin").style.display = "none";

  // 1. AÑADIDO: Ocultamos también la nueva vista de perfil
  document.getElementById("view-profile").style.display = "none";
  if (detailTimer) detailTimer.stop();
}

export function deactivateAllNavs() {
  document
    .querySelectorAll(".nav-link")
    .forEach((link) => link.classList.remove("active"));
}

export function showView(viewId, navId, detailTimer) {
  hideAllViews(detailTimer);
  deactivateAllNavs();
  document.getElementById(viewId).style.display = "block";
  if (navId) document.getElementById(navId).classList.add("active");
}

export function openModal(id) {
  document.getElementById(id).style.display = "flex";
}

export function closeModal(id) {
  document.getElementById(id).style.display = "none";
}

export function calculateSecondsLeft(dateStr) {
  if (!dateStr) return 0;
  const endDate = new Date(dateStr.replace(/-/g, "/"));
  const now = new Date();
  const diffMs = endDate - now;
  return diffMs > 0 ? Math.floor(diffMs / 1000) : 0;
}

// ==========================================
// 2. AÑADIDO: Función envoltorio para las alertas de SweetAlert2
// ==========================================
export function alerta(titulo, texto, icono = "info") {
  Swal.fire({
    title: titulo,
    text: texto,
    icon: icono,
    background: "#181818",
    color: "#d4af37",
    confirmButtonColor: "#d4af37",
    cancelButtonColor: "#333333",
    customClass: { popup: "border border-warning" },
  });
}
