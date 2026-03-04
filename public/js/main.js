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
import Chart from "https://cdn.jsdelivr.net/npm/chart.js@4.4.1/+esm";

let allArtworks = [];
let activeTimers = [];
let detailTimer = null;
let globalTickerTable = null;

// ==========================================================
// HERRAMIENTA DE FORMATO DE MONEDA (UX)
// ==========================================================
function formatearDinero(cantidad) {
  return new Intl.NumberFormat("es-ES", {
    style: "currency",
    currency: "EUR",
  }).format(cantidad);
}

document.addEventListener("DOMContentLoaded", () => {
  loadUserData();
  loadCatalog();
  setupBidForm();
  initGlobalTicker();
  setupDepositForm();
});

// EXPORTACIONES A WINDOW (Solo navegación de menús)
window.showCatalog = () => {
  showView("view-catalog", "nav-catalog-btn", detailTimer);
  loadCatalog();
};
window.showWorkshop = () => {
  showView("view-workshop", "nav-workshop-btn", detailTimer);
  loadWorkshopData();
};
window.showVault = () => {
  showView("view-vault", "nav-vault-btn", detailTimer);
  loadVaultData();
};
window.showAdmin = () => {
  showView("view-admin", "nav-admin-btn", detailTimer);
  loadAdminData();
};
window.showProfile = () => {
  showView("view-profile", null, detailTimer); // Usamos tu propio showView de ui.js
  window.loadProfileData();
};

window.logout = () => {
  window.location.href = "../index.php?accion=logout";
};

window.openDepositModal = () => openModal("modal-deposit");
window.closeDepositModal = () => closeModal("modal-deposit");

window.upgradeToArtist = () => {
  Swal.fire({
    title: "Forja tu Legado",
    text: "Se deducirán 19,99 € de tu Saldo Disponible para habilitar tu Taller de por vida. ¿Aceptas la transacción?",
    icon: "info",
    showCancelButton: true,
    confirmButtonColor: "#d4af37",
    cancelButtonColor: "#333",
    confirmButtonText: "Sí, pagar 19,99 €",
    cancelButtonText: "Cancelar",
    background: "#181818",
    color: "#d4af37",
  }).then(async (result) => {
    if (result.isConfirmed) {
      const res = await api.postJSON("ascender_artista", {});

      if (res.success) {
        alerta(
          "¡Bienvenido, Creador!",
          "Tu licencia ha sido aprobada. El Taller está abierto.",
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

window.openNewArtworkModal = openNewArtworkModal;

// ==========================================
// LÓGICA DE USUARIO Y RUTAS
// ==========================================
async function loadUserData() {
  const user = await api.get("obtener_usuario");

  // 1. MODO INVITADO (Sin sesión)
  if (user.error) {
    document.getElementById("guest-panel").classList.remove("d-none");
    document.getElementById("logged-in-panel").classList.add("d-none");
    window.currentUser = null;
    return;
  }

  // 2. MODO USUARIO REGISTRADO (Comprador, Artista, Admin)
  document.getElementById("guest-panel").classList.add("d-none");
  document.getElementById("logged-in-panel").classList.remove("d-none");
  document.getElementById("nav-username").innerText = user.nombre;
  window.currentUser = user; // Guardamos los datos para usarlos en el Perfil

  // CONTROL DE ACCESO PARA EL ADMINISTRADOR
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

  // CONTROL DE ACCESO PARA ARTISTAS
  if (user.es_artista == 1)
    document.getElementById("nav-workshop-btn").style.display = "inline-block";
}

// ==========================================
// CATÁLOGO
// ==========================================
let currentMinPrice = 0;
let currentMaxPrice = Infinity;
let currentSort = "time-asc";

async function loadCatalog() {
  try {
    await api.get("liquidar_vencidas");
  } catch (error) {
    console.warn(
      "Aviso: El motor de liquidación no respondió correctamente.",
      error,
    );
  }

  allArtworks = await api.get("obtener_catalogo");
  const sortRadios = document.querySelectorAll('input[name="sort-catalog"]');

  const applyFiltersAndSort = () => {
    let procesadas = allArtworks.filter((art) => {
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
      } else if (currentSort === "time-asc") {
        return tiempoA - tiempoB;
      }
    });

    document.getElementById("filtro-contador").innerText =
      `${procesadas.length} lotes encontrados`;
    renderCatalog(procesadas);
  };

  sortRadios.forEach((radio) => {
    radio.addEventListener("change", (e) => {
      currentSort = e.target.value;
      applyFiltersAndSort();
    });
  });

  filtroPrecios.crear("slider-precio", allArtworks, (min, max) => {
    document.getElementById("precio-min-label").innerText = `${min} €`;
    document.getElementById("precio-max-label").innerText = `${max} €`;
    currentMinPrice = min;
    currentMaxPrice = max;
    applyFiltersAndSort();
  });

  applyFiltersAndSort();
}

function renderCatalog(artworksToRender) {
  const container = document.getElementById("catalog-container");
  const template = document.getElementById("artwork-card-template");

  activeTimers.forEach((t) => t && t.stop());
  activeTimers = [];
  container.innerHTML = "";

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
      if (Number(art.precio_actual) === Number(art.precio_inicial)) {
        priceLabel.innerText = "Puja Inicial";
      } else {
        priceLabel.innerText = "Puja Actual";
      }
    } else {
      cardArticle.classList.add("terminado");
      btn.disabled = true;
      timerDisplay.classList.remove("text-gold", "text-danger");
      timerDisplay.classList.add("text-muted");

      if (art.estado === "DESIERTA") {
        priceLabel.innerText = "Subasta";
        timerDisplay.innerText = "DESIERTA";
        clone.querySelector(".card-precio-actual").innerText = "Sin ofertas";
      } else {
        priceLabel.innerText = "Precio Final";
        timerDisplay.innerText = "FINALIZADA";
      }
    }

    const t = reloj.iniciar(secondsLeft, timerDisplay, () => {
      btn.disabled = true;
      cardArticle.classList.add("terminado");
      timerDisplay.classList.remove("text-gold", "text-danger");
      timerDisplay.classList.add("text-muted");

      if (art.estado === "DESIERTA") {
        timerDisplay.innerText = "DESIERTA";
        priceLabel.innerText = "Subasta";
      } else {
        timerDisplay.innerText = "FINALIZADA";
        priceLabel.innerText = "Precio Final";
      }
    });

    activeTimers.push(t);
    container.appendChild(clone);
  });
}

async function openArtworkDetail(id_obra) {
  const data = await api.get("obtener_detalle", `&id=${id_obra}`);
  if (data.error) return alerta("Error", data.error, "error");

  showView("view-detail", null, detailTimer);
  document.getElementById("detail-title").innerText = data.titulo;
  document.getElementById("detail-image").src = data.imagen_url;
  document.getElementById("detail-desc").innerText = data.descripcion;
  document.getElementById("detail-bio").innerText = data.biografia_artista;
  document.getElementById("current-precio-actual").innerText = formatearDinero(
    data.precio_actual,
  );
  document.getElementById("input-id-obra").value = data.id_obra;

  const esPrimeraPuja = !data.history || data.history.length === 0;
  const minimoAPujar = esPrimeraPuja
    ? Number(data.precio_actual)
    : Number(data.precio_actual) + 50;

  document.getElementById("input-monto").min = minimoAPujar;
  document.getElementById("input-monto").value = minimoAPujar;

  const timerDisplay = document.getElementById("detail-timer");
  const secondsLeft = calculateSecondsLeft(data.fecha_fin);
  detailTimer = reloj.iniciar(secondsLeft, timerDisplay);

  tablas.crearHistorialObra("#artwork-bids-table", data.history || []);
  graficas.pintarEvolucion("price-chart", data.history || []);

  const bidBtn = document.getElementById("btn-submit-bid");
  const bidInput = document.getElementById("input-monto");

  // =================================================================
  // NUEVO: CALCULADORA EN VIVO DE LA PRIMA DEL COMPRADOR (12%)
  // =================================================================
  const feeCalculator = document.getElementById("fee-calculator");
  if (feeCalculator) {
    const actualizarCalculadora = () => {
      const valorPuja = parseFloat(bidInput.value) || 0;
      const totalConTasas = valorPuja * 1.12; // Sumamos el 12% de recargo
      feeCalculator.innerHTML = `Total a bloquear (incl. 12% Prima): <strong class="text-gold">${formatearDinero(totalConTasas)}</strong>`;
    };

    // Usamos oninput para no acumular listeners si se abren varias obras
    bidInput.oninput = actualizarCalculadora;

    // Forzamos el cálculo inicial al abrir la ventana con el valor por defecto
    actualizarCalculadora();
  }
  // =================================================================

  if (!window.currentUser) {
    bidBtn.disabled = true;
    bidBtn.innerText = "Regístrate para Pujar";
    bidBtn.classList.replace("btn-gold", "btn-secondary");
    bidInput.disabled = true;
  } else if (data.id_vendedor == window.currentUser.id) {
    bidBtn.disabled = true;
    bidBtn.innerText = "Esta es tu obra";
    bidBtn.classList.replace("btn-gold", "btn-danger");
    bidBtn.classList.replace("btn-secondary", "btn-danger");
    bidInput.disabled = true;
  } else {
    bidBtn.disabled = false;
    bidBtn.innerText = "Sellar Transacción";
    bidBtn.classList.replace("btn-secondary", "btn-gold");
    bidBtn.classList.replace("btn-danger", "btn-gold");
    bidInput.disabled = false;
  }
}

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
        "Transacción Sellada",
        "Su puja ha sido registrada en el libro mayor.",
        "success",
      );
    } else alerta("Transacción Rechazada", result.message, "error");
  });
}

// ==========================================
// TALLER, ADMIN Y BÓVEDA
// ==========================================
function initGlobalTicker() {
  const fetchGlobalBids = async () => {
    const data = await api.get("obtener_ticker");
    if (!globalTickerTable)
      globalTickerTable = tablas.crearTicker("#global-bids-table", data || []);
    else globalTickerTable.setData(data || []);
  };
  fetchGlobalBids();
  setInterval(fetchGlobalBids, 10000);
}

async function loadWorkshopData() {
  const container = document.getElementById("workshop-content");
  const data = await api.get("obtener_taller");

  container.innerHTML = `
    <div class="workshop-dashboard">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="text-gold h5 m-0">Mi Galería Personal</h3>
        <button onclick="openNewArtworkModal()" class="btn-gold shadow">+ Forjar Nueva Obra</button>
      </div>
      <div id="artist-gallery-grid" class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4"></div>
    </div>
  `;

  const gridContainer = document.getElementById("artist-gallery-grid");
  const template = document.getElementById("artwork-card-template");

  activeTimers.forEach((t) => t && t.stop());
  activeTimers = [];

  const obras = data.artworks || [];

  if (obras.length === 0) {
    gridContainer.innerHTML = `<p class="text-muted w-100 text-center mt-5">Aún no has forjado ninguna obra. ¡El imperio espera tu arte!</p>`;
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
    btn.innerText = art.estado === "ACTIVA" ? "Subasta en curso" : art.estado;
    btn.classList.replace("btn-gold", "btn-outline-secondary");

    const timerDisplay = clone.querySelector(".countdown-display");
    const secondsLeft = calculateSecondsLeft(art.fecha_fin);
    const t = reloj.iniciar(secondsLeft, timerDisplay, () => {
      cardArticle.classList.add("terminado");
      timerDisplay.innerText = "FINALIZADA";
      if (btn.innerText === "Subasta en curso") btn.innerText = "FINALIZADA";
    });

    activeTimers.push(t);
    gridContainer.appendChild(clone);
  });
}

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
          "Lote Forjado",
          "¡La obra ha sido enviada al Senado!",
          "success",
        );
        loadWorkshopData();
      } else alerta("Error en la Forja", result.message, "error");
    });
}

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
          "<li class='text-center text-muted'>Sin transacciones</li>";
      }
    }
  } catch (error) {
    console.error(
      "Fallo de conexión con el Oráculo Analítico (Python):",
      error,
    );
  }

  const works = await api.get("obtener_pendientes");
  tablas.crearAdminPendientes(
    "#admin-pending-table",
    works || [],
    async (id) => {
      const obra = await api.get("obtener_detalle_revision", `&id=${id}`);
      if (obra.error) {
        alerta("Error", "No se pudo cargar el expediente.", "error");
        return;
      }

      Swal.fire({
        title: `Revisión: ${obra.titulo}`,
        html: `
            <div class="text-start" style="color: #eee;">
                <img src="${obra.imagen_url}" class="img-fluid rounded mb-3 border border-secondary" style="max-height: 300px; width: 100%; object-fit: cover;">
                <p><strong>Artista:</strong> ${obra.artista_nombre}</p>
                <p><strong>Precio Salida:</strong> ${obra.precio_inicial} €</p>
                <p><strong>Descripción:</strong></p>
                <p class="text-muted small">${obra.descripcion}</p>
                <hr style="border-color: #444;">
                <p class="text-center text-gold">¿Cumple este lote con los estándares de AUREUS?</p>
            </div>
        `,
        width: "600px",
        background: "#181818",
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText:
          '<i class="fa-solid fa-check"></i> Validar y Publicar',
        denyButtonText: '<i class="fa-solid fa-xmark"></i> Rechazar',
        cancelButtonText: "Cerrar",
        confirmButtonColor: "#d4af37",
        denyButtonColor: "#dc3545",
        cancelButtonColor: "#333",
      }).then(async (result) => {
        if (result.isConfirmed) {
          const res = await api.postJSON("aprobar_obra", { id_obra: id });
          if (res.success) {
            alerta("Éxito", "La obra ya es pública en el catálogo.", "success");
            loadAdminData();
          } else {
            alerta(
              "Error",
              res.message || "No se pudo validar la obra.",
              "error",
            );
          }
        } else if (result.isDenied) {
          const res = await api.postJSON("rechazar_obra", { id_obra: id });
          if (res.success) {
            alerta(
              "Obra Rechazada",
              "Se ha denegado la entrada al catálogo.",
              "info",
            );
            loadAdminData();
          } else {
            alerta("Error", "No se pudo rechazar la obra.", "error");
          }
        }
      });
    },
  );

  const users = await api.get("obtener_usuarios");
  tablas.crearAdminUsuarios(
    "#admin-users-table",
    users || [],
    async (usuario) => {
      await api.postJSON("cambiar_rol_usuario", {
        id_usuario: usuario.id_usuario,
        rol: usuario.rol,
      });
      alerta(
        "Rol Actualizado",
        `El ciudadano ahora es ${usuario.rol}.`,
        "success",
      );
    },
    async (id) => {
      Swal.fire({
        title: "¿Revocar Acceso?",
        text: "Esta acción inhabilitará al ciudadano.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d4af37",
        cancelButtonColor: "#d33",
        confirmButtonText: "Sí, desterrar",
        background: "#181818",
        color: "#d4af37",
      }).then(async (result) => {
        if (result.isConfirmed) {
          const res = await api.postJSON("eliminar_usuario", {
            id_usuario: id,
          });
          if (res.success) {
            alerta("Expulsado", "Ciudadano desterrado del imperio.", "success");
            loadAdminData();
          } else alerta("Error", res.message, "error");
        }
      });
    },
    async (id) => {
      Swal.fire({
        title: "¿Conceder Amnistía?",
        text: "El ciudadano recuperará sus derechos en el imperio.",
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#28a745",
        cancelButtonColor: "#333",
        confirmButtonText: "Sí, perdonar",
        background: "#181818",
        color: "#d4af37",
      }).then(async (result) => {
        if (result.isConfirmed) {
          const res = await api.postJSON("amnistiar_usuario", {
            id_usuario: id,
          });
          if (res.success) {
            alerta("Amnistiado", "Ciudadano readmitido con éxito.", "success");
            loadAdminData();
          } else alerta("Error", res.message, "error");
        }
      });
    },
  );
}

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

  const bids = await api.get("obtener_mis_pujas");
  tablas.crearBoveda("#vault-bids-table", bids || [], confirmarRecepcionObra);
}

function setupDepositForm() {
  const paypalContainer = document.getElementById("paypal-button-container");
  if (!paypalContainer) return;

  paypal
    .Buttons({
      createOrder: function (data, actions) {
        const amount = document.getElementById("deposit-amount").value;
        if (amount < 10) {
          alerta(
            "Aviso Imperial",
            "El ingreso mínimo en la bóveda es de 10€",
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
            "<p class='text-gold text-center mt-3'>Validando transacción con el Senado...</p>";
          const result = await api.postJSON("capturar_pago_paypal", {
            orderID: data.orderID,
          });

          if (result.success) {
            alerta(
              "Transacción Completada",
              "Se han añadido " + result.monto_anadido + "€ a su bóveda.",
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
            alerta("Error Bancario", result.message, "error");
          }
        } catch (error) {
          console.error("Fallo crítico validando el pago:", error);
          alerta("Error de comunicación", "El servidor no responde.", "error");
        } finally {
          setTimeout(() => location.reload(), 2000);
        }
      },
    })
    .render("#paypal-button-container");
}

async function confirmarRecepcionObra(id_obra) {
  Swal.fire({
    title: "¿Confirmar Recepción?",
    text: "Al aceptar, certifícas que tienes la obra y los fondos bloqueados serán transferidos irrevocablemente al artista.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d4af37",
    cancelButtonColor: "#333",
    confirmButtonText: "Sí, he recibido la obra",
    background: "#181818",
    color: "#d4af37",
  }).then(async (result) => {
    if (result.isConfirmed) {
      const res = await api.postJSON("confirmar_recepcion", {
        id_obra: id_obra,
      });
      if (res.success) {
        alerta("¡Transacción Sellada!", res.message, "success");
        loadVaultData();
        loadUserData();
      } else {
        alerta("Aviso del Senado", res.message, "error");
      }
    }
  });
}

// ==========================================================
// 🛡️ LÓGICA DEL PERFIL DE USUARIO (FUSIONADA Y CORREGIDA)
// ==========================================================
window.loadProfileData = async function () {
  // 1. Mostrar/Ocultar el botón de Forjar Legado y pintar la gráfica
  const user = window.currentUser;
  if (user) {
    const disponible = parseFloat(user.saldo_disponible) || 0;
    const bloqueado = parseFloat(user.saldo_bloqueado) || 0;
    graficas.pintarPerfil("profile-chart", disponible, bloqueado);

    const upgradeSection = document.getElementById("upgrade-artist-section");
    if (user.rol === "comprador" && user.es_artista == 0) {
      upgradeSection.style.display = "block";
    } else {
      upgradeSection.style.display = "none";
    }
  }

  // 2. Traer los datos extra del perfil desde la BD
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
    console.error("Error cargando el expediente del ciudadano:", error);
  }
};

window.guardarBiografia = async function () {
  const bioText = document.getElementById("perfil-biografia").value;
  const res = await api.postJSON("guardar_biografia", { biografia: bioText });
  if (res.success) {
    alerta(
      "Expediente Actualizado",
      "Tu historia ha sido tallada en piedra con éxito.",
      "success",
    );
  } else {
    alerta("Error", "No se pudo actualizar la biografía.", "error");
  }
};
