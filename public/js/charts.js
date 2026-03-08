/**
 * @file charts.js
 * @description Módulo de visualización de datos. Actúa como fachada (Facade) para la librería Chart.js.
 * Gestiona la renderización, actualización y destrucción de gráficos estadísticos y financieros
 * dentro de la aplicación, asegurando la correcta liberación de memoria en el ciclo de vida del DOM.
 */

import Chart from "https://cdn.jsdelivr.net/npm/chart.js@4.4.1/auto/+esm";

/** @type {Chart|null} Instancia activa del gráfico de evolución temporal de pujas. */
let graficoEvolucion = null;

/** @type {Chart|null} Instancia activa del gráfico de distribución patrimonial del usuario. */
let graficoPerfil = null;

export const graficas = {
  /**
   * Renderiza un gráfico de líneas que representa la evolución temporal
   * del valor de una obra en función de su historial de licitaciones.
   * @param {string} idCanvas - Identificador del elemento <canvas> en el DOM.
   * @param {Array<Object>} historialPujas - Colección de registros de transacciones.
   */
  pintarEvolucion: (idCanvas, historialPujas) => {
    const canvas = document.getElementById(idCanvas);
    if (!canvas) return;

    const ctx = canvas.getContext("2d");

    // Destrucción de la instancia previa para evitar superposición y sobrecarga de memoria
    if (graficoEvolucion) {
      graficoEvolucion.destroy();
    }

    // Se invierte el historial para garantizar el orden cronológico ascendente (de más antigua a más reciente)
    const historialOrdenado = [...historialPujas].reverse();

    // Extracción de la marca temporal (HH:MM:SS) de la cadena datetime del servidor
    const etiquetas = historialOrdenado.map((h) =>
      h.fecha ? h.fecha.split(" ")[1] : "",
    );
    const datos = historialOrdenado.map((h) => parseFloat(h.monto));

    graficoEvolucion = new Chart(ctx, {
      type: "line",
      data: {
        labels: etiquetas,
        datasets: [
          {
            label: "Evolución de la Licitación (€)",
            data: datos,
            borderColor: "#d4af37",
            fill: true,
            backgroundColor: "rgba(212, 175, 55, 0.1)",
            tension: 0.2, // Ligero suavizado de la curva para mejorar la legibilidad financiera
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
      },
    });
  },

  /**
   * Renderiza un gráfico circular (Doughnut) que muestra la distribución
   * del patrimonio del usuario.
   * @param {string} idCanvas - Identificador del elemento <canvas> en el DOM.
   * @param {number} disponible - Monto de capital líquido disponible.
   * @param {number} bloqueado - Monto de capital retenido (Escrow / Pujas activas).
   */
  pintarPerfil: (idCanvas, disponible, bloqueado) => {
    const canvas = document.getElementById(idCanvas);
    if (!canvas) return;

    const ctx = canvas.getContext("2d");

    // Destrucción de la instancia previa para actualización dinámica
    if (graficoPerfil) {
      graficoPerfil.destroy();
    }

    graficoPerfil = new Chart(ctx, {
      type: "doughnut",
      data: {
        labels: ["Capital Disponible", "Capital Retenido"],
        datasets: [
          {
            data: [disponible, bloqueado],
            backgroundColor: ["#d4af37", "#555555"],
            borderColor: "#181818",
            borderWidth: 2,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: "bottom",
            labels: { color: "#e0e0e0" },
          },
        },
      },
    });
  },
};
