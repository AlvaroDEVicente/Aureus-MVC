/**
 * @file api.js
 * @description Módulo de comunicación asíncrona (AJAX). Actúa como fachada (Facade)
 * para estandarizar las peticiones Fetch hacia el Front Controller (index.php) del servidor.
 */

const RUTA_API = "../index.php?accion=";

export const api = {
  /**
   * Ejecuta una petición HTTP GET.
   * @param {string} accion - Identificador de la ruta o endpoint.
   * @param {string} [params=""] - Parámetros de consulta (Query String) opcionales.
   * @returns {Promise<Object>} Promesa que resuelve a un objeto JSON.
   */
  get: async (accion, params = "") => {
    const response = await fetch(`${RUTA_API}${accion}${params}`);
    return await response.json();
  },

  /**
   * Ejecuta una petición HTTP POST enviando un cuerpo JSON.
   * @param {string} accion - Identificador de la ruta o endpoint.
   * @param {Object} datos - Objeto de datos a serializar.
   * @returns {Promise<Object>} Promesa que resuelve a un objeto JSON.
   */
  postJSON: async (accion, datos) => {
    const response = await fetch(RUTA_API + accion, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(datos),
    });
    return await response.json();
  },

  /**
   * Ejecuta una petición HTTP POST enviando datos en formato Multipart (Archivos).
   * @param {string} accion - Identificador de la ruta o endpoint.
   * @param {FormData} formData - Objeto FormData conteniendo los binarios y campos.
   * @returns {Promise<Object>} Promesa que resuelve a un objeto JSON.
   */
  postForm: async (accion, formData) => {
    const response = await fetch(RUTA_API + accion, {
      method: "POST",
      body: formData,
    });
    return await response.json();
  },
};
