import Chart from "https://cdn.jsdelivr.net/npm/chart.js@4.4.1/+esm";

let graficoActual = null;

export const graficas = {
  pintarEvolucion: (idCanvas, historialPujas) => {
    const ctx = document.getElementById(idCanvas).getContext("2d");
    if (graficoActual) graficoActual.destroy();

    const historialOrdenado = [...historialPujas].reverse();
    const etiquetas = historialOrdenado.map((h) =>
      h.fecha ? h.fecha.split(" ")[1] : "",
    );
    const datos = historialOrdenado.map((h) => h.monto);

    graficoActual = new Chart(ctx, {
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
};
