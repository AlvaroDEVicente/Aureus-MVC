// public/js/main.js
import { api } from "./api.js";
import { showView, openModal, closeModal, calculateSecondsLeft } from "./ui.js";
// Importamos nuestras fachadas (las librerías están escondidas dentro)
import { reloj } from "./timers.js";
import { filtroPrecios } from "./slider.js";
import { graficas } from "./charts.js";
import { tablas } from "./tables.js";

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
window.logout = async () => {
  await api.get("logout");
  window.location.href = "login.php";
};
window.openDepositModal = () => openModal("modal-deposit");
window.closeDepositModal = () => closeModal("modal-deposit");
window.openNewArtworkModal = openNewArtworkModal;

async function loadUserData() {
  const user = await api.get("obtener_usuario");
  if (user.error) return (window.location.href = "login.php");
  document.getElementById("nav-username").innerText = user.nombre;
  document.getElementById("nav-saldo-disponible").innerText = Number(
    user.saldo_disponible,
  ).toFixed(2);
  document.getElementById("nav-saldo-bloqueado").innerText = Number(
    user.saldo_bloqueado,
  ).toFixed(2);
  document.getElementById("nav-vault-btn").style.display = "inline-block";
  if (user.es_artista == 1)
    document.getElementById("nav-workshop-btn").style.display = "inline-block";
  if (user.rol === "admin") {
    document.getElementById("nav-admin-btn").style.display = "inline-block";
    document.getElementById("nav-vault-btn").style.display = "none";
  }
}

async function loadCatalog() {
  allArtworks = await api.get("obtener_catalogo");

  // Usamos nuestro módulo de Slider
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

    // Usamos nuestro módulo de Reloj
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
  if (data.error) return alert(data.error);

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

  // Usamos nuestro módulo de Reloj
  detailTimer = reloj.iniciar(secondsLeft, timerDisplay);

  // Usamos nuestros módulos de Tablas y Gráficas
  tablas.crearHistorialObra("#artwork-bids-table", data.history || []);
  graficas.pintarEvolucion("price-chart", data.history || []);
}

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
  container.innerHTML = `<div class="workshop-dashboard"><div style="display: flex; justify-content: space-between; align-items: center;" class="mb-4"><h3 class="text-gold" style="margin:0;">Resumen de Actividad</h3><button onclick="openNewArtworkModal()" class="btn-gold">+ Forjar Nueva Obra</button></div><div id="artist-inventory-table"></div></div>`;
  tablas.crearTaller("#artist-inventory-table", data.artworks || []);
}

async function loadAdminData() {
  const works = await api.get("obtener_pendientes");
  // Pasamos el Callback de aprobar como parámetro
  tablas.crearAdminPendientes(
    "#admin-pending-table",
    works || [],
    async (id) => {
      if (!confirm("¿Deseas validar esta obra para su publicación inmediata?"))
        return;
      const result = await api.postJSON("aprobar_obra", { id_obra: id });
      if (result.success) {
        alert("Lote validado.");
        loadAdminData();
      } else alert("Error: " + result.message);
    },
  );

  const users = await api.get("obtener_usuarios");
  // Pasamos los Callbacks de cambiar rol y borrar como parámetros
  tablas.crearAdminUsuarios(
    "#admin-users-table",
    users || [],
    async (usuario) => {
      await api.postJSON("cambiar_rol_usuario", {
        id_usuario: usuario.id_usuario,
        rol: usuario.rol,
      });
      alert("Rol actualizado a " + usuario.rol);
    },
    async (id) => {
      if (!confirm("¿Seguro que deseas desterrar a este ciudadano?")) return;
      const result = await api.postJSON("eliminar_usuario", { id_usuario: id });
      if (result.success) {
        alert("Ciudadano expulsado.");
        loadAdminData();
      } else alert("Fallo al eliminar: " + result.message);
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
    } else alert("Error: " + result.message);
  });
}

function setupDepositForm() {
  document
    .getElementById("form-deposit")
    .addEventListener("submit", async (e) => {
      e.preventDefault();
      const result = await api.postJSON("ingresar_fondos", {
        monto: document.getElementById("deposit-amount").value,
      });
      if (result.success) {
        alert("Transferencia completada.");
        closeModal("modal-deposit");
        loadUserData();
        if (document.getElementById("view-vault").style.display === "block")
          loadVaultData();
      } else alert("Error: " + result.message);
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
        alert("¡Obra forjada!");
        loadWorkshopData();
      } else alert("Error: " + result.message);
    });
}
