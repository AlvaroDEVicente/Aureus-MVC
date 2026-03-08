/**
 * @file timers.js
 * @description Implementación de una fachada para la librería easytimer.js.
 * Gestiona el ciclo de vida de los temporizadores de cuenta regresiva en las tarjetas
 * de subasta y asegura la ejecución de callbacks al finalizar el tiempo.
 */

import { Timer } from "https://cdn.jsdelivr.net/npm/easytimer.js@4.6.0/+esm";

export const reloj = {
  /**
   * Instancia e inyecta un cronómetro en el DOM.
   * @param {number} segundos - Tiempo restante para la expiración.
   * @param {HTMLElement} elementoHTML - Nodo del DOM donde se renderizará el texto.
   * @param {Function} [onTerminado] - Función de resolución asíncrona opcional.
   * @returns {Timer|null} Instancia del objeto Timer o null si la subasta ya finalizó.
   */
  iniciar: (segundos, elementoHTML, onTerminado) => {
    if (segundos <= 0) {
      elementoHTML.innerText = "FINALIZADA";
      elementoHTML.classList.replace("text-gold", "text-danger");
      if (onTerminado) onTerminado();
      return null;
    }

    const timer = new Timer();
    timer.start({ countdown: true, startValues: { seconds: segundos } });

    timer.addEventListener("secondsUpdated", () => {
      let dias = timer.getTimeValues().days;
      let horas = timer.getTimeValues().hours;
      elementoHTML.innerText =
        dias > 0
          ? `${dias} días, ${horas} horas`
          : timer.getTimeValues().toString();
    });

    timer.addEventListener("targetAchieved", () => {
      elementoHTML.innerText = "FINALIZADA";
      elementoHTML.classList.replace("text-gold", "text-danger");
      if (onTerminado) onTerminado();
    });

    return timer;
  },
};
