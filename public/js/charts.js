import Chart from "https://cdn.jsdelivr.net/npm/chart.js@4.4.1/auto/+esm";

let graficoEvolucion = null;
let graficoPerfil = null;

export const graficas = {
  // Gráfica de línea para la Ficha de la Obra
  pintarEvolucion: (idCanvas, historialPujas) => {
    const ctx = document.getElementById(idCanvas).getContext("2d");
    if (graficoEvolucion) graficoEvolucion.destroy();

    const historialOrdenado = [...historialPujas].reverse();
    const etiquetas = historialOrdenado.map((h) =>
      h.fecha ? h.fecha.split(" ")[1] : "",
    );
    const datos = historialOrdenado.map((h) => h.monto);

    graficoEvolucion = new Chart(ctx, {
      type: "line",
      data: {
        labels: etiquetas,
        datasets: [
          {
            label: "Evolución €",
            data: datos,
            borderColor: "#d4af37",
            fill: true,
            backgroundColor: "rgba(212, 175, 55, 0.1)",
          },
        ],
      },
      options: { responsive: true },
    });
  },

  // Gráfica Donut para el Perfil del Usuario
  pintarPerfil: (idCanvas, disponible, bloqueado) => {
    const ctx = document.getElementById(idCanvas).getContext("2d");
    if (graficoPerfil) graficoPerfil.destroy();

    graficoPerfil = new Chart(ctx, {
      type: "doughnut",
      data: {
        labels: ["Saldo Disponible", "Capital Bloqueado"],
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
        plugins: {
          legend: { position: "bottom", labels: { color: "#e0e0e0" } },
        },
      },
    });
  },
};
