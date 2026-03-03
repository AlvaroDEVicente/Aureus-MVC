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
  showView("view-profile", null, detailTimer);
  loadProfileData();
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
      // Llamada a la API (que crearemos en el siguiente paso)
      const res = await api.postJSON("ascender_artista", {});

      if (res.success) {
        alerta(
          "¡Bienvenido, Creador!",
          "Tu licencia ha sido aprobada. El Taller está abierto.",
          "success",
        );
        // Recargamos los datos para que aparezca la pestaña Taller en el menú
        await loadUserData();
        showProfile();
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
    return; // Salimos de la función, pero nos quedamos en la página para ver el catálogo
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
    // Ocultamos la caja de saldos (Responsive)
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
// VISTA: PERFIL (NUEVO)
// ==========================================
function loadProfileData() {
  const user = window.currentUser;
  if (!user) return;

  document.getElementById("profile-rol").innerText = user.rol;

  // Extraemos los valores
  const disponible = parseFloat(user.saldo_disponible) || 0;
  const bloqueado = parseFloat(user.saldo_bloqueado) || 0;

  // Llamamos a nuestro módulo de gráficas
  graficas.pintarPerfil("profile-chart", disponible, bloqueado);

  // Mostrar el botón de ascenso SOLO si es un comprador puro
  const upgradeSection = document.getElementById("upgrade-artist-section");
  if (user.rol === "comprador" && user.es_artista == 0) {
    upgradeSection.style.display = "block";
  } else {
    upgradeSection.style.display = "none";
  }
}

// ==========================================
// CATÁLOGO
// ==========================================
// Variables globales para recordar el estado actual
let currentMinPrice = 0;
let currentMaxPrice = Infinity;
let currentSort = "time-asc"; // Por defecto, ordenamos por urgencia

async function loadCatalog() {
  allArtworks = await api.get("obtener_catalogo");
  const sortRadios = document.querySelectorAll('input[name="sort-catalog"]');

  // Función maestra que FILTRA y luego ORDENA
  const applyFiltersAndSort = () => {
    // 1. Filtrar por el presupuesto del Slider
    let procesadas = allArtworks.filter((art) => {
      return (
        Number(art.precio_actual) >= currentMinPrice &&
        Number(art.precio_actual) <= currentMaxPrice
      );
    });

    // 2. Ordenar el array resultante (Prioridad 1: Activas, Prioridad 2: Radio Buttons)
    procesadas.sort((a, b) => {
      const ahora = new Date().getTime();
      const tiempoA = new Date(a.fecha_fin).getTime();
      const tiempoB = new Date(b.fecha_fin).getTime();

      // Comprobamos si las obras ya han caducado
      const terminadaA = tiempoA <= ahora;
      const terminadaB = tiempoB <= ahora;

      // --- CAPA 1: LAS FINALIZADAS AL FONDO ---
      if (terminadaA && !terminadaB) return 1; // 'a' está terminada, va abajo
      if (!terminadaA && terminadaB) return -1; // 'b' está terminada, va abajo

      // --- CAPA 2: EL ORDEN DEL USUARIO (Para obras en el mismo estado) ---
      if (currentSort === "price-asc") {
        return Number(a.precio_actual) - Number(b.precio_actual);
      } else if (currentSort === "price-desc") {
        return Number(b.precio_actual) - Number(a.precio_actual);
      } else if (currentSort === "time-asc") {
        return tiempoA - tiempoB;
      }
    });

    // 3. Actualizar la vista
    document.getElementById("filtro-contador").innerText =
      `${procesadas.length} lotes encontrados`;
    renderCatalog(procesadas);
  };

  // Evento 1: Cuando el usuario hace clic en los Radio Buttons
  sortRadios.forEach((radio) => {
    radio.addEventListener("change", (e) => {
      currentSort = e.target.value;
      applyFiltersAndSort();
    });
  });

  // Evento 2: Cuando el usuario mueve el Slider de Precios
  filtroPrecios.crear("slider-precio", allArtworks, (min, max) => {
    document.getElementById("precio-min-label").innerText = `${min} €`;
    document.getElementById("precio-max-label").innerText = `${max} €`;
    currentMinPrice = min;
    currentMaxPrice = max;
    applyFiltersAndSort();
  });

  // Pintamos el catálogo por primera vez aplicando el orden por defecto
  applyFiltersAndSort();
}

function renderCatalog(artworksToRender) {
  const container = document.getElementById("catalog-container");
  const template = document.getElementById("artwork-card-template");

  // Limpiamos los temporizadores activos para no sobrecargar el navegador
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
    const priceLabel = clone.querySelector(".price-row .text-muted"); // Capturamos el texto "Puja Actual"
    const secondsLeft = calculateSecondsLeft(art.fecha_fin);

    // Si la obra ya está finalizada al cargar la página, cambiamos el texto del tirón
    if (secondsLeft <= 0) {
      priceLabel.innerText = "Precio Final";
    }

    // Iniciamos el reloj y definimos qué pasa cuando llega a cero
    const t = reloj.iniciar(secondsLeft, timerDisplay, () => {
      btn.disabled = true;
      cardArticle.classList.add("terminado");

      // Apagamos los colores
      timerDisplay.classList.remove("text-gold", "text-danger");
      timerDisplay.classList.add("text-muted");
      timerDisplay.innerText = "FINALIZADA";

      // Cambiamos el texto en directo si se acaba el tiempo mientras miramos
      priceLabel.innerText = "Precio Final";
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
  document.getElementById("input-monto").min = Number(data.precio_actual) + 50;
  document.getElementById("input-monto").value =
    Number(data.precio_actual) + 50;

  const timerDisplay = document.getElementById("detail-timer");
  const secondsLeft = calculateSecondsLeft(data.fecha_fin);
  detailTimer = reloj.iniciar(secondsLeft, timerDisplay);

  tablas.crearHistorialObra("#artwork-bids-table", data.history || []);
  graficas.pintarEvolucion("price-chart", data.history || []);

  // BLOQUEO DE SEGURIDAD: Invitados y Propietarios
  const bidBtn = document.getElementById("btn-submit-bid");
  const bidInput = document.getElementById("input-monto");

  if (!window.currentUser) {
    // Visitante sin cuenta
    bidBtn.disabled = true;
    bidBtn.innerText = "Regístrate para Pujar";
    bidBtn.classList.replace("btn-gold", "btn-secondary");
    bidInput.disabled = true;
  } else if (data.id_vendedor == window.currentUser.id) {
    // Creador intentando pujar por su propia obra
    bidBtn.disabled = true;
    bidBtn.innerText = "Esta es tu obra";
    bidBtn.classList.replace("btn-gold", "btn-danger");
    bidBtn.classList.replace("btn-secondary", "btn-danger");
    bidInput.disabled = true;
  } else {
    // Comprador legítimo
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

  // Preparamos el HTML: Cabecera y el "Grid" donde irán las tarjetas
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

  // Limpiamos temporizadores antiguos
  activeTimers.forEach((t) => t && t.stop());
  activeTimers = [];

  const obras = data.artworks || [];

  if (obras.length === 0) {
    gridContainer.innerHTML = `<p class="text-muted w-100 text-center mt-5">Aún no has forjado ninguna obra. ¡El imperio espera tu arte!</p>`;
    return;
  }

  // Pintamos las tarjetas
  obras.forEach((art) => {
    const clone = template.content.cloneNode(true);
    const cardArticle = clone.querySelector("article");

    clone.querySelector(".card-title").innerText = art.titulo;
    clone.querySelector(".card-image").src = art.imagen_url;
    clone.querySelector(".card-precio-actual").innerText = formatearDinero(
      art.precio_actual,
    );

    const btn = clone.querySelector(".card-btn");
    btn.disabled = true; // Botón inactivo, solo informativo
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
  // ==========================================================
  // 🟢 Llamada al Microservicio OLAP (Python FastAPI)
  // ==========================================================
  try {
    const resPython = await fetch(
      "http://localhost:8000/api/analytics/dashboard",
    );
    const jsonAnalytics = await resPython.json();

    if (jsonAnalytics.success) {
      const eco = jsonAnalytics.data.economico;
      const users = jsonAnalytics.data.usuarios;

      // -- Panel Económico --
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

      // -- Panel de Usuarios --
      document.getElementById("kpi-total-usuarios").innerText =
        users.total_usuarios;
      document.getElementById("kpi-nuevos-usuarios").innerText =
        `+ ${users.nuevos_semana}`;
      document.getElementById("kpi-total-artistas").innerText =
        users.total_artistas;

      // -- Ranking Top Mecenas --
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
  // ==========================================================

  const works = await api.get("obtener_pendientes");
  tablas.crearAdminPendientes(
    "#admin-pending-table",
    works || [],
    async (id) => {
      // 1. Buscamos los datos completos de la obra para revisarla
      const obra = await api.get("obtener_detalle_revision", `&id=${id}`);

      if (obra.error) {
        alerta("Error", "No se pudo cargar el expediente.", "error");
        return;
      }

      // 2. Mostramos el "Expediente de Revisión" completo
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
        confirmButtonText:
          '<i class="fa-solid fa-check"></i> Validar y Publicar',
        cancelButtonText: "Cerrar",
        confirmButtonColor: "#d4af37",
        cancelButtonColor: "#333",
      }).then(async (result) => {
        if (result.isConfirmed) {
          // 3. Si confirma, ejecutamos la aprobación real
          const res = await api.postJSON("aprobar_obra", { id_obra: id });
          if (res.success) {
            alerta("Éxito", "La obra ya es pública en el catálogo.", "success");
            loadAdminData(); // Recargamos la tabla del senado
          } else {
            alerta(
              "Error",
              res.message || "No se pudo validar la obra.",
              "error",
            );
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
      // 🚨 CONFIRMACIÓN DE DESTERRAMIENTO CON SWEETALERT 🚨
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
      // 🟢 NUEVA FUNCIÓN: AMNISTIAR (Desbanear) 🟢
      Swal.fire({
        title: "¿Conceder Amnistía?",
        text: "El ciudadano recuperará sus derechos en el imperio.",
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#28a745", // Verde esperanza
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

  // Sumamos los valores numéricos y luego formateamos el resultado
  const total =
    parseFloat(user.saldo_disponible) + parseFloat(user.saldo_bloqueado);
  document.getElementById("vault-total").innerText = formatearDinero(total);

  const bids = await api.get("obtener_mis_pujas");
  tablas.crearBoveda("#vault-bids-table", bids || []);
}

function setupDepositForm() {
  const paypalContainer = document.getElementById("paypal-button-container");

  // Si no estamos en la vista que tiene el modal (por si acaso), no hacemos nada
  if (!paypalContainer) return;

  paypal
    .Buttons({
      // 1. Configurar la transacción cuando el Mecenas hace clic
      createOrder: function (data, actions) {
        const amount = document.getElementById("deposit-amount").value;

        if (amount < 10) {
          // Usamos la alerta bonita de vuestro proyecto
          alerta(
            "Aviso Imperial",
            "El ingreso mínimo en la bóveda es de 10€",
            "warning",
          );
          return false; // Detiene a PayPal
        }

        return actions.order.create({
          purchase_units: [
            {
              amount: { value: amount },
            },
          ],
        });
      },

      // 2. ¿Qué pasa cuando el Mecenas aprueba el pago en la ventana de PayPal?
      onApprove: async function (data, actions) {
        try {
          // Mensaje de espera mientras el backend verifica
          paypalContainer.innerHTML =
            "<p class='text-gold text-center mt-3'>Validando transacción con el Senado...</p>";

          // Usamos la misma función api.postJSON que ya teníais
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
          // Recargamos la página pasados 2 segundos para limpiar el modal
          setTimeout(() => location.reload(), 2000);
        }
      },
    })
    .render("#paypal-button-container");
}
