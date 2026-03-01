import noUiSlider from "https://cdn.jsdelivr.net/npm/nouislider@15.7.1/+esm";

export const filtroPrecios = {
  crear: (idElemento, obras, onCambio) => {
    const slider = document.getElementById(idElemento);
    if (!slider) return;
    if (slider.noUiSlider) slider.noUiSlider.destroy();

    let maxPrice =
      obras.length > 0
        ? Math.max(...obras.map((a) => Number(a.precio_actual)))
        : 1000;
    maxPrice = Math.ceil(maxPrice / 100) * 100;

    noUiSlider.create(slider, {
      start: [0, maxPrice],
      connect: true,
      step: 10,
      range: { min: 0, max: maxPrice },
    });

    slider.noUiSlider.on("update", (values) => {
      onCambio(parseInt(values[0]), parseInt(values[1]));
    });
  },
};
