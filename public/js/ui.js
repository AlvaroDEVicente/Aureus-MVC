/**
 * @file ui.js
 * @description Subsistema encargado de la manipulación directa del DOM,
 * conmutación de vistas (Enrutamiento visual) y gestión de notificaciones
 * del sistema mediante SweetAlert2.
 */

/**
 * Oculta todas las secciones de tipo 'view-' de la SPA.
 * Destruye o pausa el temporizador de detalle activo para prevenir colisiones de estado.
 * @param {Timer} [detailTimer] - Temporizador activo en la vista de detalle.
 */
export function hideAllViews(detailTimer) {
  const views = [
    "view-catalog",
    "view-detail",
    "view-workshop",
    "view-vault",
    "view-admin",
    "view-profile",
  ];
  views.forEach((id) => (document.getElementById(id).style.display = "none"));
  if (detailTimer) detailTimer.stop();
}

/**
 * Restablece el estado visual de los elementos de navegación superior.
 */
export function deactivateAllNavs() {
  document
    .querySelectorAll(".nav-link")
    .forEach((link) => link.classList.remove("active"));
}

/**
 * Orquesta la transición entre vistas, activando el nodo solicitado.
 * @param {string} viewId - Identificador del contenedor a mostrar.
 * @param {string} [navId] - Identificador del enlace del menú a resaltar.
 * @param {Timer} [detailTimer] - Temporizador activo.
 */
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

/**
 * Calcula la diferencia en segundos entre la fecha de finalización y el momento actual.
 * @param {string} dateStr - Fecha en formato ISO o SQL.
 * @returns {number} Diferencia en segundos. Retorna 0 si la fecha ya ha pasado.
 */
export function calculateSecondsLeft(dateStr) {
  if (!dateStr) return 0;
  const endDate = new Date(dateStr.replace(/-/g, "/"));
  const now = new Date();
  const diffMs = endDate - now;
  return diffMs > 0 ? Math.floor(diffMs / 1000) : 0;
}

/**
 * Fachada para el disparo estandarizado de notificaciones de sistema.
 * @param {string} titulo - Encabezado del modal.
 * @param {string} texto - Cuerpo o detalle del mensaje.
 * @param {string} [icono="info"] - Tipo de alerta ('success', 'error', 'warning', 'info').
 */
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
