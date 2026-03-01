// ==========================================
// AUREUS - Módulo API (js/api.js)
// ==========================================
const RUTA_API = "../index.php?accion=";

export const api = {
  // Peticiones GET simples
  get: async (accion, params = "") => {
    const response = await fetch(`${RUTA_API}${accion}${params}`);
    return await response.json();
  },

  // Peticiones POST enviando JSON (para pujas, login, roles...)
  postJSON: async (accion, datos) => {
    const response = await fetch(RUTA_API + accion, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(datos),
    });
    return await response.json();
  },

  // Peticiones POST para formularios con archivos (subir obra)
  postForm: async (accion, formData) => {
    const response = await fetch(RUTA_API + accion, {
      method: "POST",
      body: formData,
    });
    return await response.json();
  },
};
