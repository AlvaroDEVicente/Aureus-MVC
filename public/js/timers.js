import { Timer } from "https://cdn.jsdelivr.net/npm/easytimer.js@4.6.0/+esm";

export const reloj = {
  // Crea un reloj y lo inyecta en un elemento HTML
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
