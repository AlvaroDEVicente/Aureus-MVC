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
window.openNewArtworkModal = openNewArtworkModal;

// ==========================================
// LÓGICA DE USUARIO Y RUTAS
// ==========================================
async function loadUserData() {
  const user = await api.get("obtener_usuario");
  if (user.error) return (window.location.href = "login.php");

  document.getElementById("nav-username").innerText = user.nombre;
  window.currentUser = user; // Guardamos los datos para usarlos en el Perfil

  // 🔒 CONTROL DE ACCESO PARA EL ADMINISTRADOR
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
    document.getElementById("nav-saldo-disponible").innerText = Number(
      user.saldo_disponible,
    ).toFixed(2);
    document.getElementById("nav-saldo-bloqueado").innerText = Number(
      user.saldo_bloqueado,
    ).toFixed(2);
    document.getElementById("nav-vault-btn").style.display = "inline-block";
  }

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

  // Llamamos a nuestro módulo de gráficas (¡Cero errores!)
  graficas.pintarPerfil("profile-chart", disponible, bloqueado);
}

// ==========================================
// CATÁLOGO
// ==========================================
async function loadCatalog() {
  allArtworks = await api.get("obtener_catalogo");
  filtroPrecios.crear("slider-precio", allArtworks, (min, max) => {
    document.getElementById("precio-min-label").innerText = `${min} €`;
    document.getElementById("precio-max-label").innerText = `${max} €`;
    const filtradas = allArtworks.filter(
      (art) =>
        Number(art.precio_actual) >= min && Number(art.precio_actual) <= max,
    );
    document.getElementById("filtro-contador").innerText =
      `${filtradas.length} lotes encontrados`;
    renderCatalog(filtradas);
  });
  renderCatalog(allArtworks);
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
    clone.querySelector(".card-image").src = art.imagen_url;
    clone.querySelector(".card-precio-actual").innerText = Number(
      art.precio_actual,
    ).toFixed(2);

    const btn = clone.querySelector(".card-btn");
    btn.addEventListener("click", () => openArtworkDetail(art.id_obra));

    const timerDisplay = clone.querySelector(".countdown-display");
    const secondsLeft = calculateSecondsLeft(art.fecha_fin);
    const t = reloj.iniciar(secondsLeft, timerDisplay, () => {
      btn.disabled = true;
      cardArticle.classList.add("terminado");
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
  document.getElementById("current-precio-actual").innerText = Number(
    data.precio_actual,
  ).toFixed(2);
  document.getElementById("input-id-obra").value = data.id_obra;
  document.getElementById("input-monto").min = Number(data.precio_actual) + 50;
  document.getElementById("input-monto").value =
    Number(data.precio_actual) + 50;

  const timerDisplay = document.getElementById("detail-timer");
  const secondsLeft = calculateSecondsLeft(data.fecha_fin);
  detailTimer = reloj.iniciar(secondsLeft, timerDisplay);

  tablas.crearHistorialObra("#artwork-bids-table", data.history || []);
  graficas.pintarEvolucion("price-chart", data.history || []);
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
      document.getElementById("nav-saldo-disponible").innerText = Number(
        result.nuevo_saldo_disponible,
      ).toFixed(2);
      document.getElementById("nav-saldo-bloqueado").innerText = Number(
        result.nuevo_saldo_bloqueado,
      ).toFixed(2);
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
  container.innerHTML = `<div class="workshop-dashboard"><div class="d-flex justify-content-between align-items-center mb-4"><h3 class="text-gold h5 m-0">Resumen de Actividad</h3><button onclick="openNewArtworkModal()" class="btn-gold">+ Forjar Nueva Obra</button></div><div id="artist-inventory-table"></div></div>`;
  tablas.crearTaller("#artist-inventory-table", data.artworks || []);
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
  const works = await api.get("obtener_pendientes");
  tablas.crearAdminPendientes(
    "#admin-pending-table",
    works || [],
    async (id) => {
      // Usamos SweetAlert2 para la confirmación de validación
      Swal.fire({
        title: "¿Validar Obra?",
        text: "La obra será publicada en el catálogo.",
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#d4af37",
        cancelButtonColor: "#333",
        background: "#181818",
        color: "#d4af37",
      }).then(async (result) => {
        if (result.isConfirmed) {
          const res = await api.postJSON("aprobar_obra", { id_obra: id });
          if (res.success) {
            alerta("Aprobado", "Lote validado exitosamente.", "success");
            loadAdminData();
          } else alerta("Error", res.message, "error");
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
  );
}

async function loadVaultData() {
  const user = await api.get("obtener_usuario");
  document.getElementById("vault-available").innerText =
    Number(user.saldo_disponible).toFixed(2) + " €";
  document.getElementById("vault-blocked").innerText =
    Number(user.saldo_bloqueado).toFixed(2) + " €";
  document.getElementById("vault-total").innerText =
    (Number(user.saldo_disponible) + Number(user.saldo_bloqueado)).toFixed(2) +
    " €";
  const bids = await api.get("obtener_mis_pujas");
  tablas.crearBoveda("#vault-bids-table", bids || []);
}

function setupDepositForm() {
  const paypalContainer = document.getElementById('paypal-button-container');
  
  // Si no estamos en la vista que tiene el modal (por si acaso), no hacemos nada
  if (!paypalContainer) return;

  paypal.Buttons({
    // 1. Configurar la transacción cuando el Mecenas hace clic
    createOrder: function(data, actions) {
      const amount = document.getElementById("deposit-amount").value;
      
      if (amount < 10) {
        // Usamos la alerta bonita de vuestro proyecto
        alerta("Aviso Imperial", "El ingreso mínimo en la bóveda es de 10€", "warning");
        return false; // Detiene a PayPal
      }
      
      return actions.order.create({
        purchase_units: [{
          amount: { value: amount }
        }]
      });
    },
    
    // 2. ¿Qué pasa cuando el Mecenas aprueba el pago en la ventana de PayPal?
    onApprove: async function(data, actions) {
      try {
        // Mensaje de espera mientras el backend verifica
        paypalContainer.innerHTML = "<p class='text-gold text-center mt-3'>Validando transacción con el Senado...</p>";

        // Usamos la misma función api.postJSON que ya teníais
        const result = await api.postJSON("capturar_pago_paypal", {
          orderID: data.orderID
        });

        if (result.success) {
          alerta(
            "Transacción Completada",
            "Se han añadido " + result.monto_anadido + "€ a su bóveda.",
            "success"
          );
          closeModal("modal-deposit");
          loadUserData();
          if (document.getElementById("view-vault").style.display === "block") {
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
    }
  }).render('#paypal-button-container');
}
