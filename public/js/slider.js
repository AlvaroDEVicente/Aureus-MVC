/**
 * @file slider.js
 * @description Módulo encargado de instanciar y sincronizar el componente de
 * filtrado bidireccional de precios (Rango dinámico) utilizando la librería noUiSlider.
 */

import noUiSlider from "https://cdn.jsdelivr.net/npm/nouislider@15.7.1/+esm";

export const filtroPrecios = {
  /**
   * Crea y vincula el control deslizante a la vista actual.
   * @param {string} idElemento - ID del contenedor DOM para el slider.
   * @param {Array} obras - Colección actual de obras para calcular los límites numéricos.
   * @param {Function} onCambio - Función de retorno (Callback) ejecutada tras cada actualización.
   */
  crear: (idElemento, obras, onCambio) => {
    const slider = document.getElementById(idElemento);
    const inputMin = document.getElementById("input-precio-min");
    const inputMax = document.getElementById("input-precio-max");

    if (!slider || !inputMin || !inputMax) return;

    // Destrucción preventiva para evitar fugas de memoria o múltiples instancias
    if (slider.noUiSlider) slider.noUiSlider.destroy();

    // Cálculo dinámico del límite superior redondeado a la centena más cercana
    let maxPrice =
      obras.length > 0
        ? Math.max(...obras.map((a) => Number(a.precio_actual)))
        : 1000;
    if (maxPrice <= 0) maxPrice = 1000;
    maxPrice = Math.ceil(maxPrice / 100) * 100;

    noUiSlider.create(slider, {
      start: [0, maxPrice],
      connect: true,
      step: 10,
      range: { min: 0, max: maxPrice },
    });

    // Enlace de eventos: Actualización de inputs al mover el slider
    slider.noUiSlider.on("update", (values, handle) => {
      let value = parseInt(values[handle]);
      if (handle === 0) {
        inputMin.value = value;
      } else {
        inputMax.value = value;
      }
      onCambio(parseInt(values[0]), parseInt(values[1]));
    });

    // Enlace de eventos: Actualización del slider al teclear en los inputs
    inputMin.addEventListener("change", function () {
      slider.noUiSlider.set([this.value, null]);
    });

    inputMax.addEventListener("change", function () {
      slider.noUiSlider.set([null, this.value]);
    });
  },
};
