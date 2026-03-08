/**
 * @file main.js
 * @description Módulo principal y punto de entrada de la Single Page Application (SPA).
 * Gestiona el estado global, el enrutamiento visual, la inicialización de componentes
 * y la coordinación entre la interfaz de usuario (UI) y las llamadas a la API REST.
 */

import { api } from "./api.js";
import {
  showView,
  openModal,
  closeModal,
  calculateSecondsLeft,
  alerta,
} from "./ui.js";
import { reloj } from "./timers.js";
import { filtroPrecios } from "./slider.js";
import { graficas } from "./charts.js";
import { tablas } from "./tables.js";

// ==========================================================
// VARIABLES GLOBALES DE ESTADO
// ==========================================================

/** @type {Array<Object>} Colección en memoria del catálogo activo. */
let allArtworks = [];

/** @type {Array<Timer>} Colección de temporizadores activos en la vista actual. */
let activeTimers = [];

/** @type {Timer|null} Temporizador único para la vista de detalle de obra. */
let detailTimer = null;

/** @type {Tabulator|null} Instancia de la tabla del Ticker de actividad global. */
let globalTickerTable = null;

// Variables de estado para el filtrado del catálogo
let currentMinPrice = 0;
let currentMaxPrice = Infinity;
let currentSort = "time-asc";
let catalogEventsAttached = false;

// ==========================================================
// UTILIDADES COMUNES
// ==========================================================

/**
 * Formatea un valor numérico a estándar europeo de moneda (EUR).
 * @param {number|string} cantidad - Valor a formatear.
 * @returns {string} Cadena formateada (ej. "1.234,50 €").
 */
function formatearDinero(cantidad) {
  return new Intl.NumberFormat("es-ES", {
    style: "currency",
    currency: "EUR",
  }).format(cantidad);
}

// ==========================================================
// INICIALIZACIÓN DEL SISTEMA (BOOTSTRAP)
// ==========================================================

document.addEventListener("DOMContentLoaded", async () => {
  // 1. Autenticación y resolución de privilegios
  await loadUserData();

  // 2. Recuperación de estado de navegación (Persistencia F5)
  const rutaGuardada = sessionStorage.getItem("aureus_route");

  // 3. Enrutamiento basado en estado y control de acceso (RBAC)
  if (
    rutaGuardada === "admin" &&
    window.currentUser &&
    window.currentUser.rol === "admin"
  ) {
    window.showAdmin();
  } else if (rutaGuardada === "vault" && window.currentUser) {
    window.showVault();
  } else if (
    rutaGuardada === "workshop" &&
    window.currentUser &&
    window.currentUser.es_artista == 1
  ) {
    window.showWorkshop();
  } else if (rutaGuardada === "profile" && window.currentUser) {
    window.showProfile();
  } else if (rutaGuardada && rutaGuardada.startsWith("detail_")) {
    const idObra = rutaGuardada.split("_")[1];
    openArtworkDetail(idObra);
  } else {
    window.showCatalog(); // Enrutamiento por defecto
  }

  // 4. Inicialización de listeners y sub-módulos globales
  setupBidForm();
  initGlobalTicker();
  setupDepositForm();
});

// ==========================================================
// EXPOSICIÓN DE MÉTODOS AL OBJETO WINDOW (PARA EVENTOS INLINE HTML)
// ==========================================================

window.showCatalog = () => {
  sessionStorage.setItem("aureus_route", "catalog");
  showView("view-catalog", "nav-catalog-btn", detailTimer);
  loadCatalog();
};

window.showWorkshop = () => {
  sessionStorage.setItem("aureus_route", "workshop");
  showView("view-workshop", "nav-workshop-btn", detailTimer);
  loadWorkshopData();
};

window.showVault = () => {
  sessionStorage.setItem("aureus_route", "vault");
  showView("view-vault", "nav-vault-btn", detailTimer);
  loadVaultData();
};

window.showAdmin = () => {
  sessionStorage.setItem("aureus_route", "admin");
  showView("view-admin", "nav-admin-btn", detailTimer);
  loadAdminData();
};

window.showProfile = () => {
  sessionStorage.setItem("aureus_route", "profile");
  showView("view-profile", null, detailTimer);
  window.loadProfileData();
};

window.logout = () => {
  sessionStorage.removeItem("aureus_route");
  window.location.href = "../index.php?accion=logout";
};

window.openDepositModal = () => openModal("modal-deposit");
window.closeDepositModal = () => closeModal("modal-deposit");
window.openNewArtworkModal = openNewArtworkModal;

/**
 * Inicia el flujo de adquisición de licencia comercial.
 * Interactúa con la API para aplicar los cargos correspondientes y escalar privilegios.
 */
window.upgradeToArtist = () => {
  Swal.fire({
    title: "Forja tu Legado",
    text: "Se deducirán 19,99 € de tu Saldo Disponible para habilitar tu Taller de por vida. ¿Aceptas la transacción?",
    icon: "info",
    showCancelButton: true,
    confirmButtonColor: "#d4af37",
    cancelButtonColor: "#333",
    confirmButtonText: "Sí, adquirir licencia (19,99 €)",
    cancelButtonText: "Cancelar",
    background: "#181818",
    color: "#d4af37",
  }).then(async (result) => {
    if (result.isConfirmed) {
      const res = await api.postJSON("ascender_artista", {});
      if (res.success) {
        alerta(
          "¡Bienvenido, Creador!",
          "Tu licencia ha sido aprobada. El Taller está operativo.",
          "success",
        );
        await loadUserData();
        window.showProfile();
      } else {
        alerta("Transacción Rechazada", res.message, "error");
      }
    }
  });
};

// ==========================================================
// LÓGICA DE USUARIO Y SESIÓN
// ==========================================================

/**
 * Solicita y renderiza los datos del usuario autenticado en la barra de navegación.
 * Gestiona la visibilidad de los paneles según los permisos concedidos.
 */
async function loadUserData() {
  const user = await api.get("obtener_usuario");

  if (user.error) {
    document.getElementById("guest-panel").classList.remove("d-none");
    document.getElementById("logged-in-panel").classList.add("d-none");
    window.currentUser = null;
    return;
  }

  document.getElementById("guest-panel").classList.add("d-none");
  document.getElementById("logged-in-panel").classList.remove("d-none");
  document.getElementById("nav-username").innerText = user.nombre;
  window.currentUser = user;

  if (user.rol === "admin") {
    document.getElementById("nav-admin-btn").style.display = "inline-block";
    document.getElementById("nav-vault-btn").style.display = "none";
    const balancesBox = document.getElementById("user-balances");
    if (balancesBox) {
      balancesBox.classList.remove("d-lg-block");
      balancesBox.style.display = "none";
    }
  } else {
    document.getElementById("nav-saldo-disponible").innerText = formatearDinero(
      user.saldo_disponible,
    );
    document.getElementById("nav-saldo-bloqueado").innerText = formatearDinero(
      user.saldo_bloqueado,
    );
    document.getElementById("nav-vault-btn").style.display = "inline-block";
  }

  if (user.es_artista == 1) {
    document.getElementById("nav-workshop-btn").style.display = "inline-block";
  }
}

// ==========================================================
// MÓDULO CATÁLOGO Y RENDERIZADO
// ==========================================================

/**
 * Descarga el catálogo maestro, inicializa el motor de cronjobs del servidor
 * y delega el filtrado y ordenación al estado local de la SPA.
 */
async function loadCatalog() {
  // Ejecución silenciosa del proceso de liquidación para obras expiradas
  try {
    await api.get("liquidar_vencidas");
  } catch (error) {
    console.warn(
      "Aviso del Sistema: Proceso de liquidación latente fallido.",
      error,
    );
  }

  allArtworks = await api.get("obtener_catalogo");
  // Si la API devuelve error en lugar de array (ej. sin obras), lo forzamos a un array vacío
  const safeArtworks = Array.isArray(allArtworks) ? allArtworks : [];

  const applyFiltersAndSort = () => {
    let procesadas = safeArtworks.filter((art) => {
      return (
        Number(art.precio_actual) >= currentMinPrice &&
        Number(art.precio_actual) <= currentMaxPrice
      );
    });

    procesadas.sort((a, b) => {
      const ahora = new Date().getTime();
      const tiempoA = new Date(a.fecha_fin).getTime();
      const tiempoB = new Date(b.fecha_fin).getTime();

      const terminadaA = tiempoA <= ahora;
      const terminadaB = tiempoB <= ahora;

      if (terminadaA && !terminadaB) return 1;
      if (!terminadaA && terminadaB) return -1;

      if (currentSort === "price-asc") {
        return Number(a.precio_actual) - Number(b.precio_actual);
      } else if (currentSort === "price-desc") {
        return Number(b.precio_actual) - Number(a.precio_actual);
      } else {
        return tiempoA - tiempoB;
      }
    });

    document.getElementById("filtro-contador").innerText =
      `${procesadas.length} lotes detectados`;
    renderCatalog(procesadas);
  };

  if (!catalogEventsAttached) {
    const sortRadios = document.querySelectorAll('input[name="sort-catalog"]');
    sortRadios.forEach((radio) => {
      radio.addEventListener("change", (e) => {
        currentSort = e.target.value;
        applyFiltersAndSort();
      });
    });
    catalogEventsAttached = true;
  }

  filtroPrecios.crear("slider-precio", safeArtworks, (min, max) => {
    currentMinPrice = min;
    currentMaxPrice = max;
    applyFiltersAndSort();
  });
}

/**
 * Mapea la colección de obras procesadas hacia la estructura DOM del catálogo.
 * Instancia y registra los temporizadores de cuenta atrás de forma independiente.
 * @param {Array<Object>} artworksToRender - Colección final a mostrar.
 */
function renderCatalog(artworksToRender) {
  const container = document.getElementById("catalog-container");
  const template = document.getElementById("artwork-card-template");

  // Liberación de recursos de temporizadores previos
  activeTimers.forEach((t) => t && t.stop());
  activeTimers = [];
  container.innerHTML = "";

  if (artworksToRender.length === 0) {
    container.innerHTML = `<div class="col-12 text-center text-muted mt-5"><i class="fa-solid fa-palette fs-1 mb-3 text-gold"></i><br>El catálogo imperial está vacío en este momento.</div>`;
    return;
  }

  artworksToRender.forEach((art) => {
    const clone = template.content.cloneNode(true);
    const cardArticle = clone.querySelector("article");

    clone.querySelector(".card-title").innerText = art.titulo;
    clone.querySelector(".card-image").src =
      art.imagen_url || "./img/default_obra.png";
    clone.querySelector(".card-precio-actual").innerText = formatearDinero(
      art.precio_actual,
    );

    const btn = clone.querySelector(".card-btn");
    btn.addEventListener("click", () => openArtworkDetail(art.id_obra));

    const timerDisplay = clone.querySelector(".countdown-display");
    const priceLabel = clone.querySelector(".price-row .text-muted");
    const secondsLeft = calculateSecondsLeft(art.fecha_fin);

    if (secondsLeft > 0) {
      priceLabel.innerText =
        Number(art.precio_actual) === Number(art.precio_inicial)
          ? "Tasación Inicial"
          : "Puja Líder";
    } else {
      cardArticle.classList.add("terminado");
      btn.disabled = true;
      timerDisplay.classList.remove("text-gold", "text-danger");
      timerDisplay.classList.add("text-muted");

      if (art.estado === "DESIERTA") {
        priceLabel.innerText = "Resolución";
        timerDisplay.innerText = "LOTE DESIERTO";
        clone.querySelector(".card-precio-actual").innerText = "Sin actividad";
      } else {
        priceLabel.innerText = "Adjudicación";
        timerDisplay.innerText = "SUBASTA FINALIZADA";
      }
    }

    const t = reloj.iniciar(secondsLeft, timerDisplay, () => {
      btn.disabled = true;
      cardArticle.classList.add("terminado");
      timerDisplay.classList.remove("text-gold", "text-danger");
      timerDisplay.classList.add("text-muted");

      if (art.estado === "DESIERTA") {
        timerDisplay.innerText = "LOTE DESIERTO";
        priceLabel.innerText = "Resolución";
      } else {
        timerDisplay.innerText = "SUBASTA FINALIZADA";
        priceLabel.innerText = "Adjudicación";
      }
    });

    activeTimers.push(t);
    container.appendChild(clone);
  });
}

// ==========================================================
// MÓDULO DE NEGOCIACIÓN (DETALLE DE OBRA)
// ==========================================================

/**
 * Carga la vista pormenorizada de una obra y prepara el entorno para la negociación.
 * @param {string|number} id_obra - Identificador único de registro.
 */
async function openArtworkDetail(id_obra) {
  sessionStorage.setItem("aureus_route", `detail_${id_obra}`);

  const data = await api.get("obtener_detalle", `&id=${id_obra}`);
  if (data.error) return alerta("Acceso Denegado", data.error, "error");

  showView("view-detail", null, detailTimer);
  document.getElementById("detail-title").innerText = data.titulo;
  document.getElementById("detail-image").src = data.imagen_url;
  document.getElementById("detail-desc").innerText = data.descripcion;
  document.getElementById("detail-bio").innerText = data.biografia_artista;
  document.getElementById("current-precio-actual").innerText = formatearDinero(
    data.precio_actual,
  );
  document.getElementById("input-id-obra").value = data.id_obra;

  // CÁLCULO DE REGLA DE NEGOCIO: Puja inicial igual al precio de salida, o incremento de +50€ sobre puja existente.
  const esPrimeraPuja = !data.history || data.history.length === 0;
  const minimoAPujar = esPrimeraPuja
    ? Number(data.precio_actual)
    : Number(data.precio_actual) + 50;

  const bidBtn = document.getElementById("btn-submit-bid");
  const bidInput = document.getElementById("input-monto");

  bidInput.min = minimoAPujar;
  bidInput.value = minimoAPujar;

  const timerDisplay = document.getElementById("detail-timer");
  const secondsLeft = calculateSecondsLeft(data.fecha_fin);

  // Callback de bloqueo en tiempo real para la Condición de Carrera
  const onTimerEnd = () => {
    if (bidBtn) {
      bidBtn.disabled = true;
      bidBtn.innerText = "Licitación Cerrada";
      bidBtn.classList.replace("btn-gold", "btn-secondary");
      bidBtn.classList.replace("btn-danger", "btn-secondary");
    }
    if (bidInput) {
      bidInput.disabled = true;
    }
  };

  detailTimer = reloj.iniciar(secondsLeft, timerDisplay, onTimerEnd);

  // SANITIZACIÓN PARA TABULATOR Y CHART.JS
  const safeHistory = Array.isArray(data.history) ? data.history : [];
  tablas.crearHistorialObra("#artwork-bids-table", safeHistory);
  graficas.pintarEvolucion("price-chart", safeHistory);

  const feeCalculator = document.getElementById("fee-calculator");

  if (feeCalculator) {
    const actualizarCalculadora = () => {
      const valorPuja = parseFloat(bidInput.value) || 0;
      const totalConTasas = valorPuja * 1.12; // Inclusión matemática del 12% (Prima de riesgo)
      feeCalculator.innerHTML = `Retención requerida (12% Prima): <strong class="text-gold">${formatearDinero(totalConTasas)}</strong>`;
    };
    bidInput.oninput = actualizarCalculadora;
    actualizarCalculadora();
  }

  // Lógica de control de interfaz basada en estado y privilegios al cargar la vista
  if (secondsLeft <= 0) {
    onTimerEnd(); // Reutilizamos la función de bloqueo
  } else if (!window.currentUser) {
    bidBtn.disabled = true;
    bidBtn.innerText = "Identificación Requerida";
    bidBtn.classList.replace("btn-gold", "btn-secondary");
    bidInput.disabled = true;
  } else if (data.id_vendedor == window.currentUser.id) {
    bidBtn.disabled = true;
    bidBtn.innerText = "Autoría Registrada";
    bidBtn.classList.replace("btn-gold", "btn-danger");
    bidBtn.classList.replace("btn-secondary", "btn-danger");
    bidInput.disabled = true;
  } else {
    bidBtn.disabled = false;
    bidBtn.innerText = "Confirmar Mandato";
    bidBtn.classList.replace("btn-secondary", "btn-gold");
    bidBtn.classList.replace("btn-danger", "btn-gold");
    bidInput.disabled = false;
  }
}

/**
 * Vincula el evento de envío del formulario de pujas y gestiona su respuesta.
 */
function setupBidForm() {
  document.getElementById("bid-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    const payload = {
      id_obra: parseInt(document.getElementById("input-id-obra").value),
      monto: parseFloat(document.getElementById("input-monto").value),
    };
    const result = await api.postJSON("sellar_transaccion", payload);

    if (result.success) {
      document.getElementById("nav-saldo-disponible").innerText =
        formatearDinero(result.nuevo_saldo_disponible);
      document.getElementById("nav-saldo-bloqueado").innerText =
        formatearDinero(result.nuevo_saldo_bloqueado);
      openArtworkDetail(payload.id_obra);
      alerta(
        "Contrato Registrado",
        "Su propuesta ha sido asegurada en el libro mayor.",
        "success",
      );
    } else {
      alerta("Operación Denegada", result.message, "error");
    }
  });
}

// ==========================================================
// MÓDULOS DE ÁREA PRIVADA (TALLER, BÓVEDA, PERFIL, ADMIN)
// ==========================================================

/**
 * Inicializa y mantiene sincronizado el listado lateral de actividad global.
 */
function initGlobalTicker() {
  const fetchGlobalBids = async () => {
    const data = await api.get("obtener_ticker");
    const safeData = Array.isArray(data) ? data : [];

    if (!globalTickerTable) {
      globalTickerTable = tablas.crearTicker("#global-bids-table", safeData);
    } else {
      globalTickerTable.setData(safeData);
    }
  };
  fetchGlobalBids();
  setInterval(fetchGlobalBids, 10000); // Polling cada 10 segundos
}

/**
 * Genera la vista y controles para el entorno de creadores.
 */
async function loadWorkshopData() {
  const container = document.getElementById("workshop-content");
  const data = await api.get("obtener_taller");

  container.innerHTML = `
    <div class="workshop-dashboard">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="text-gold h5 m-0">Inventario Custodiado</h3>
        <button onclick="openNewArtworkModal()" class="btn-gold shadow">+ Declarar Nueva Creación</button>
      </div>
      <div id="artist-gallery-grid" class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4"></div>
    </div>
  `;

  const gridContainer = document.getElementById("artist-gallery-grid");
  const template = document.getElementById("artwork-card-template");

  activeTimers.forEach((t) => t && t.stop());
  activeTimers = [];

  const obras = Array.isArray(data.artworks) ? data.artworks : [];

  if (obras.length === 0) {
    gridContainer.innerHTML = `<div class="col-12 text-center text-muted mt-4"><i class="fa-solid fa-palette fs-2 mb-2 text-gold"></i><br>Ausencia de registros activos.</div>`;
    return;
  }

  obras.forEach((art) => {
    const clone = template.content.cloneNode(true);
    const cardArticle = clone.querySelector("article");

    clone.querySelector(".card-title").innerText = art.titulo;
    clone.querySelector(".card-image").src = art.imagen_url;
    clone.querySelector(".card-precio-actual").innerText = formatearDinero(
      art.precio_actual,
    );

    const btn = clone.querySelector(".card-btn");
    btn.disabled = true;
    btn.innerText = art.estado === "ACTIVA" ? "Emisión en Curso" : art.estado;
    btn.classList.replace("btn-gold", "btn-outline-secondary");

    const timerDisplay = clone.querySelector(".countdown-display");
    const secondsLeft = calculateSecondsLeft(art.fecha_fin);
    const t = reloj.iniciar(secondsLeft, timerDisplay, () => {
      cardArticle.classList.add("terminado");
      timerDisplay.innerText = "PLAZO CUMPLIDO";
      if (btn.innerText === "Emisión en Curso")
        btn.innerText = "ESPERANDO RESOLUCIÓN";
    });

    activeTimers.push(t);
    gridContainer.appendChild(clone);
  });
}

/**
 * Despliega el formulario para la declaración de una nueva obra.
 */
function openNewArtworkModal() {
  const container = document.getElementById("workshop-content");
  container.innerHTML = "";
  container.appendChild(
    document.getElementById("form-subir-obra-template").content.cloneNode(true),
  );

  document
    .getElementById("form-nueva-obra")
    .addEventListener("submit", async (e) => {
      e.preventDefault();
      const result = await api.postForm("subir_obra", new FormData(e.target));
      if (result.success) {
        alerta(
          "Aprobación Pendiente",
          "Declaración registrada. El Senado revisará su aptitud.",
          "success",
        );
        loadWorkshopData();
      } else {
        alerta("Trámite Fallido", result.message, "error");
      }
    });
}

/**
 * Orquesta la descarga de métricas analíticas e instancias de administración.
 */
async function loadAdminData() {
  try {
    const resPython = await fetch(
      "http://localhost:8000/api/analytics/dashboard",
    );
    const jsonAnalytics = await resPython.json();

    if (jsonAnalytics.success) {
      const eco = jsonAnalytics.data.economico;
      const users = jsonAnalytics.data.usuarios;

      document.getElementById("kpi-volumen").innerText = formatearDinero(
        eco.volumen_negocio,
      );
      document.getElementById("kpi-mercado").innerText =
        `${eco.mercado_activas} / ${eco.mercado_finalizadas}`;
      document.getElementById("kpi-precio-medio").innerText = formatearDinero(
        eco.precio_medio,
      );
      document.getElementById("kpi-custodia").innerText = formatearDinero(
        eco.capital_custodia,
      );

      document.getElementById("kpi-total-usuarios").innerText =
        users.total_usuarios;
      document.getElementById("kpi-nuevos-usuarios").innerText =
        `+ ${users.nuevos_semana}`;
      document.getElementById("kpi-total-artistas").innerText =
        users.total_artistas;

      const topList = document.getElementById("kpi-top-mecenas");
      topList.innerHTML = "";
      if (users.top_mecenas.length > 0) {
        users.top_mecenas.forEach((mecenas, i) => {
          topList.innerHTML += `<li class="mb-1"><strong class="text-gold">#${i + 1}</strong> ${mecenas.nombre} <span class="float-end">${formatearDinero(mecenas.total_invertido)}</span></li>`;
        });
      } else {
        topList.innerHTML =
          "<li class='text-center text-muted'>Carencia de datos estadísticos</li>";
      }
    }
  } catch (error) {
    console.error(
      "Incomunicación con el Servicio de Análisis (Python API):",
      error,
    );
  }

  // Delegación a tablas.js para renderizado de vistas complejas
  // SANITIZACIÓN: Aseguramos Array vacío si no hay resultados
  const works = await api.get("obtener_pendientes");
  const safeWorks = Array.isArray(works) ? works : [];

  tablas.crearAdminPendientes("#admin-pending-table", safeWorks, async (id) => {
    const obra = await api.get("obtener_detalle_revision", `&id=${id}`);
    if (obra.error)
      return alerta(
        "Fallo de Acceso",
        "No se pudo recuperar el dossier técnico.",
        "error",
      );

    Swal.fire({
      title: `Inspección: ${obra.titulo}`,
      html: `
          <div class="text-start" style="color: #eee;">
              <img src="${obra.imagen_url}" class="img-fluid rounded mb-3 border border-secondary" style="max-height: 300px; width: 100%; object-fit: cover;">
              <p><strong>Autor registrado:</strong> ${obra.artista_nombre}</p>
              <p><strong>Tasación Base:</strong> ${formatearDinero(obra.precio_inicial)}</p>
              <p><strong>Declaración de Autenticidad:</strong></p>
              <p class="text-muted small">${obra.descripcion}</p>
              <hr style="border-color: #444;">
              <p class="text-center text-gold">¿Se autoriza la integración al catálogo público?</p>
          </div>
      `,
      width: "600px",
      background: "#181818",
      showCancelButton: true,
      showDenyButton: true,
      confirmButtonText: '<i class="fa-solid fa-check"></i> Autorizar',
      denyButtonText: '<i class="fa-solid fa-xmark"></i> Denegar',
      cancelButtonText: "Posponer",
      confirmButtonColor: "#d4af37",
      denyButtonColor: "#dc3545",
      cancelButtonColor: "#333",
    }).then(async (result) => {
      if (result.isConfirmed) {
        const res = await api.postJSON("aprobar_obra", { id_obra: id });
        if (res.success) {
          alerta(
            "Resolución Favorable",
            "Publicación efectiva inmediata.",
            "success",
          );
          loadAdminData();
        } else alerta("Incidencia", res.message, "error");
      } else if (result.isDenied) {
        const res = await api.postJSON("rechazar_obra", { id_obra: id });
        if (res.success) {
          alerta(
            "Dictamen Negativo",
            "Registro archivado y bloqueado.",
            "info",
          );
          loadAdminData();
        } else
          alerta(
            "Incidencia",
            "No se pudo alterar el estado del registro.",
            "error",
          );
      }
    });
  });

  const users = await api.get("obtener_usuarios");
  const safeUsers = Array.isArray(users) ? users : [];
  tablas.crearAdminUsuarios(
    "#admin-users-table",
    safeUsers,
    async (usuario) => {
      await api.postJSON("cambiar_rol_usuario", {
        id_usuario: usuario.id_usuario,
        rol: usuario.rol,
      });
      alerta(
        "Ajuste de Privilegios",
        `Credenciales de ${usuario.rol} asignadas.`,
        "success",
      );
    },
    async (id) => {
      Swal.fire({
        title: "¿Procesar Inhabilitación?",
        text: "La entidad perderá todos los derechos operativos.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d4af37",
        cancelButtonColor: "#d33",
        confirmButtonText: "Confirmar Cese",
        background: "#181818",
        color: "#d4af37",
      }).then(async (result) => {
        if (result.isConfirmed) {
          const res = await api.postJSON("eliminar_usuario", {
            id_usuario: id,
          });
          if (res.success) {
            alerta("Registro Terminado", "Entidad inhabilitada.", "success");
            loadAdminData();
          } else alerta("Excepción", res.message, "error");
        }
      });
    },
    async (id) => {
      Swal.fire({
        title: "¿Conceder Amnistía?",
        text: "Los derechos operativos serán restituidos.",
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#28a745",
        cancelButtonColor: "#333",
        confirmButtonText: "Ejecutar Rehabilitación",
        background: "#181818",
        color: "#d4af37",
      }).then(async (result) => {
        if (result.isConfirmed) {
          const res = await api.postJSON("amnistiar_usuario", {
            id_usuario: id,
          });
          if (res.success) {
            alerta(
              "Rehabilitación Aprobada",
              "Operativa estándar restaurada.",
              "success",
            );
            loadAdminData();
          } else alerta("Excepción", res.message, "error");
        }
      });
    },
  );
}

/**
 * Procesa la información financiera y el libro mayor del usuario autenticado.
 */
async function loadVaultData() {
  const user = await api.get("obtener_usuario");
  document.getElementById("vault-available").innerText = formatearDinero(
    user.saldo_disponible,
  );
  document.getElementById("vault-blocked").innerText = formatearDinero(
    user.saldo_bloqueado,
  );

  const total =
    parseFloat(user.saldo_disponible) + parseFloat(user.saldo_bloqueado);
  document.getElementById("vault-total").innerText = formatearDinero(total);

  // SANITIZACIÓN: Aseguramos Array vacío para disparar placeholders de Tabulator
  const bids = await api.get("obtener_mis_pujas");
  const safeBids = Array.isArray(bids) ? bids : [];
  tablas.crearBoveda("#vault-bids-table", safeBids, confirmarRecepcionObra);

  const historial = await api.get("obtener_historial_financiero");
  const safeHistorial = Array.isArray(historial) ? historial : [];
  tablas.crearHistorialTransacciones(
    "#vault-transactions-table",
    safeHistorial,
  );
}

/**
 * Inicializa el SDK de PayPal para la inyección segura de capital.
 */
function setupDepositForm() {
  const paypalContainer = document.getElementById("paypal-button-container");
  if (!paypalContainer) return;

  paypal
    .Buttons({
      createOrder: function (data, actions) {
        const amount = document.getElementById("deposit-amount").value;
        if (amount < 10) {
          alerta(
            "Restricción Aplicada",
            "Monto inferior al mínimo permitido (10€).",
            "warning",
          );
          return false;
        }
        return actions.order.create({
          purchase_units: [{ amount: { value: amount } }],
        });
      },
      onApprove: async function (data, actions) {
        try {
          paypalContainer.innerHTML =
            "<p class='text-gold text-center mt-3'>Sincronizando certificación con la matriz bancaria...</p>";
          const result = await api.postJSON("capturar_pago_paypal", {
            orderID: data.orderID,
          });

          if (result.success) {
            alerta(
              "Depósito Verificado",
              `Adición confirmada: ${formatearDinero(result.monto_anadido)}`,
              "success",
            );
            closeModal("modal-deposit");
            loadUserData();
            if (
              document.getElementById("view-vault").style.display === "block"
            ) {
              loadVaultData();
            }
          } else {
            alerta("Incidencia de Transmisión", result.message, "error");
          }
        } catch (error) {
          console.error(
            "Excepción en la integración con pasarela exterior:",
            error,
          );
          alerta(
            "Fallo Crítico",
            "Desconexión con el servidor de pago.",
            "error",
          );
        } finally {
          setTimeout(() => location.reload(), 2000);
        }
      },
    })
    .render("#paypal-button-container");
}

/**
 * Dispara el proceso de resolución del Escrow.
 * @param {number} id_obra - Identificador de la transacción bloqueada.
 */
async function confirmarRecepcionObra(id_obra) {
  Swal.fire({
    title: "¿Ratificar Conformidad?",
    text: "La confirmación liberará irreversiblemente el capital retenido en favor del creador.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d4af37",
    cancelButtonColor: "#333",
    confirmButtonText: "Certificar Recepción Físico",
    background: "#181818",
    color: "#d4af37",
  }).then(async (result) => {
    if (result.isConfirmed) {
      const res = await api.postJSON("confirmar_recepcion", {
        id_obra: id_obra,
      });
      if (res.success) {
        alerta("Escrow Finalizado", res.message, "success");
        loadVaultData();
        loadUserData();
      } else {
        alerta("Conflicto Estructural", res.message, "error");
      }
    }
  });
}

/**
 * Obtiene y estructura el perfil cívico del usuario.
 */
window.loadProfileData = async function () {
  const user = window.currentUser;
  if (user) {
    const disponible = parseFloat(user.saldo_disponible) || 0;
    const bloqueado = parseFloat(user.saldo_bloqueado) || 0;
    graficas.pintarPerfil("profile-chart", disponible, bloqueado);

    const upgradeSection = document.getElementById("upgrade-artist-section");
    upgradeSection.style.display =
      user.rol === "comprador" && user.es_artista == 0 ? "block" : "none";
  }

  try {
    const res = await api.get("obtener_mi_perfil");
    if (res && !res.error) {
      document.getElementById("perfil-nombre").innerText = res.nombre;
      document.getElementById("perfil-email").innerText = res.email;
      document.getElementById("perfil-dni").innerText = res.dni;
      document.getElementById("perfil-rol").innerText = res.rol;
      document.getElementById("perfil-biografia").value = res.biografia || "";
    }
  } catch (error) {
    console.error("Fallo de recuperación de datos filiatorios:", error);
  }
};

/**
 * Transmite la actualización de los datos biográficos.
 */
window.guardarBiografia = async function () {
  const bioText = document.getElementById("perfil-biografia").value;
  const res = await api.postJSON("guardar_biografia", { biografia: bioText });
  if (res.success) {
    alerta(
      "Datos Consignados",
      "Expediente alterado correctamente.",
      "success",
    );
  } else {
    alerta("Denegación", "Imposibilidad de persistir los cambios.", "error");
  }
};

// ==========================================================
// MÓDULO EXCEPCIONAL ADMINISTRATIVO (MODO DIOS / OVERRIDE)
// ==========================================================

window.toggleGodMode = () => {
  const panel = document.getElementById("god-mode-panel");
  panel.classList.toggle("d-none");
};

window.adminSumarFondos = async () => {
  const users = await api.get("obtener_usuarios");
  if (!users || users.error)
    return alerta(
      "Incompatibilidad",
      "Listado maestro no disponible.",
      "error",
    );

  let optionsHTML =
    '<option value="" disabled selected>Identificar receptor...</option>';
  users.forEach((u) => {
    optionsHTML += `<option value="${u.id_usuario}">UID[${u.id_usuario}] - ${u.nombre} (${u.email})</option>`;
  });

  const { value: formValues } = await Swal.fire({
    title: "Ejecución de Inyección Monetaria",
    html:
      `<select id="swal-input-id-user" class="form-select bg-dark text-gold border-secondary mb-3 w-75 mx-auto">${optionsHTML}</select>` +
      `<input id="swal-input-dinero" type="number" class="form-control bg-dark text-light border-secondary w-75 mx-auto" placeholder="Especificar volumen (€)">`,
    focusConfirm: false,
    showCancelButton: true,
    background: "#181818",
    color: "#d4af37",
    confirmButtonText: "Materializar Depósito",
    cancelButtonText: "Descartar",
    preConfirm: () => {
      const id = document.getElementById("swal-input-id-user").value;
      const dinero = document.getElementById("swal-input-dinero").value;
      if (!id)
        Swal.showValidationMessage("Selección de identidad obligatoria.");
      if (!dinero) Swal.showValidationMessage("Monto numérico requerido.");
      return [id, dinero];
    },
  });

  if (formValues) {
    const res = await api.postJSON("admin_sumar_fondos", {
      id_usuario: formValues[0],
      cantidad: formValues[1],
    });
    if (res.success) {
      alerta("Aprobado", "Integración de saldos forzada ejecutada.", "success");
      loadAdminData();
    } else alerta("Desviación", res.message, "error");
  }
};

window.adminModificarTiempo = async () => {
  const activas = await api.get("obtener_catalogo");
  const pendientes = await api.get("obtener_pendientes");
  const todasLasObras = [...(activas || []), ...(pendientes || [])];

  let optionsHTML =
    '<option value="" disabled selected>Localizar vector de destino...</option>';
  todasLasObras.forEach((o) => {
    optionsHTML += `<option value="${o.id_obra}">LOTE[${o.id_obra}] - ${o.titulo} (${o.estado || "PENDIENTE"})</option>`;
  });

  const { value: formValues } = await Swal.fire({
    title: "Manipulación Temporal Cronológica",
    html:
      `<select id="swal-input-id-obra" class="form-select bg-dark text-gold border-secondary mb-3 w-100">${optionsHTML}</select>` +
      `<label class="text-muted small d-block mb-1 text-start">Reasignación límite (ISO Datetime):</label>` +
      `<input id="swal-input-fecha" type="datetime-local" class="form-control bg-dark text-light border-secondary w-100">`,
    focusConfirm: false,
    showCancelButton: true,
    background: "#181818",
    color: "#d4af37",
    confirmButtonText: "Sobrescribir Cierre",
    preConfirm: () => {
      const id = document.getElementById("swal-input-id-obra").value;
      const fecha = document.getElementById("swal-input-fecha").value;
      if (!id)
        Swal.showValidationMessage("Defina el punto focal de la operación.");
      if (!fecha)
        Swal.showValidationMessage("Asigne parámetros temporales absolutos.");
      return [id, fecha];
    },
  });

  if (formValues) {
    const res = await api.postJSON("admin_modificar_tiempo", {
      id_obra: formValues[0],
      fecha_fin: formValues[1],
    });
    if (res.success) {
      alerta(
        "Sobrescritura Positiva",
        "El margen de operación ha mutado.",
        "success",
      );
      loadAdminData();
      loadCatalog();
    } else alerta("Rechazo de Parámetros", res.message, "error");
  }
};

window.adminBorrarObra = async () => {
  const activas = await api.get("obtener_catalogo");
  const pendientes = await api.get("obtener_pendientes");
  const todasLasObras = [...(activas || []), ...(pendientes || [])];

  let optionsHTML =
    '<option value="" disabled selected>Indicar objetivo de purga...</option>';
  todasLasObras.forEach((o) => {
    optionsHTML += `<option value="${o.id_obra}">LOTE[${o.id_obra}] - ${o.titulo}</option>`;
  });

  const { value: id_obra } = await Swal.fire({
    title: "Iniciando Protocolo de Erradicación",
    text: "La estructura de datos de este elemento será físicamente destruida.",
    icon: "warning",
    html: `<select id="swal-delete-obra" class="form-select bg-dark text-gold border-danger mt-3 w-100">${optionsHTML}</select>`,
    showCancelButton: true,
    confirmButtonColor: "#dc3545",
    confirmButtonText: "Proceder",
    background: "#181818",
    color: "#d4af37",
    preConfirm: () => {
      const id = document.getElementById("swal-delete-obra").value;
      if (!id)
        Swal.showValidationMessage(
          "Especifique un lote válido para su aislamiento.",
        );
      return id;
    },
  });

  if (id_obra) {
    const res = await api.postJSON("admin_borrar_obra", { id_obra: id_obra });
    if (res.success) {
      alerta(
        "Desvinculado",
        "El objeto ha cesado en su existencia tabular.",
        "success",
      );
      loadAdminData();
      loadCatalog();
    } else alerta("Contención Activa", res.message, "error");
  }
};
