/**
 * @file tables.js
 * @description Módulo de configuración e instanciación de tablas interactivas.
 */

import { TabulatorFull as Tabulator } from "https://cdn.jsdelivr.net/npm/tabulator-tables@5.5.0/+esm";

// ============================================================================
// FIX CSS PARA TABULATOR:
// 1. Evita que la tabla se aplaste a 0px (min-height: 60px).
// 2. Centra el placeholder sin ocupar espacio extra cuando hay datos.
// ============================================================================
const style = document.createElement("style");
style.innerHTML = `
  .tabulator .tabulator-tableholder {
    min-height: 50px !important; 
  }
  .tabulator .tabulator-placeholder {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 100% !important;
  }
  .tabulator .tabulator-placeholder span {
    color: #888 !important;
    font-size: 0.95rem !important;
    font-style: italic;
  }
`;
document.head.appendChild(style);

const FORMATO_MONEDA = {
  decimal: ",",
  thousand: ".",
  symbol: " €",
  symbolAfter: "p",
};

export const tablas = {
  crearTicker: (id, datos) =>
    new Tabulator(id, {
      data: datos,
      layout: "fitColumns",
      placeholder: "<span>Sin actividad reciente.</span>",
      columns: [
        { title: "Obra", field: "titulo_obra", widthGrow: 1 },
        {
          title: "Valor",
          field: "monto",
          width: 110,
          hozAlign: "right",
          headerHozAlign: "right",
          formatter: "money",
          formatterParams: FORMATO_MONEDA,
        },
      ],
    }),

  crearHistorialObra: (id, datos) =>
    new Tabulator(id, {
      data: datos,
      layout: "fitColumns",
      placeholder: "<span>Aún no hay pujas. ¡Sé el primero!</span>",
      columns: [
        { title: "Mecenas", field: "nombre_usuario" },
        {
          title: "Monto",
          field: "monto",
          formatter: "money",
          formatterParams: FORMATO_MONEDA,
        },
      ],
    }),

  crearTaller: (id, datos) =>
    new Tabulator(id, {
      data: datos,
      layout: "fitColumns",
      placeholder: "<span>Tu taller está vacío. ¡Forja tu primera obra!</span>",
      columns: [
        { title: "Obra", field: "titulo", widthGrow: 2 },
        {
          title: "Precio Actual",
          field: "precio_actual",
          formatter: "money",
          formatterParams: FORMATO_MONEDA,
        },
        { title: "Estado", field: "estado", hozAlign: "center" },
      ],
    }),

  crearBoveda: (id, datos, cbConfirmar) =>
    new Tabulator(id, {
      data: datos,
      layout: "fitColumns",
      placeholder: "<span>Aún no has participado en ninguna subasta.</span>",
      columns: [
        { title: "Obra", field: "titulo", widthGrow: 2 },
        {
          title: "Mi Puja",
          field: "mi_monto",
          formatter: "money",
          formatterParams: FORMATO_MONEDA,
        },
        { title: "Estado", field: "estado_puja" },
        {
          title: "Acción",
          formatter: (cell) => {
            const row = cell.getData();
            if (
              row.estado === "FINALIZADA" &&
              row.estado_puja === "Adjudicada (En tránsito)"
            ) {
              return `<button class="btn-gold" style="padding: 4px 8px; font-size: 0.8rem;">Recibida</button>`;
            }
            return "";
          },
          hozAlign: "center",
          headerSort: false,
          cellClick: (e, cell) => {
            const row = cell.getData();
            if (
              row.estado === "FINALIZADA" &&
              row.estado_puja === "Adjudicada (En tránsito)"
            ) {
              cbConfirmar(row.id_obra);
            }
          },
        },
      ],
    }),

  crearAdminPendientes: (id, datos, cbAprobar) =>
    new Tabulator(id, {
      data: datos,
      layout: "fitColumns",
      placeholder: "<span>No hay obras pendientes de revisión.</span>",
      columns: [
        { title: "Obra", field: "titulo", widthGrow: 2 },
        { title: "Artista", field: "nombre_artista", widthGrow: 2 },
        {
          title: "Precio Salida",
          field: "precio_inicial",
          formatter: "money",
          formatterParams: FORMATO_MONEDA,
        },
        {
          title: "Acción",
          formatter: () =>
            `<button class="btn-gold" style="padding: 5px 10px; font-size: 0.8rem;">Validar</button>`,
          hozAlign: "center",
          headerSort: false,
          cellClick: (e, cell) => cbAprobar(cell.getData().id_obra),
        },
      ],
    }),

  crearAdminUsuarios: (id, datos, cbCambioRol, cbBorrar, cbAmnistiar) =>
    new Tabulator(id, {
      data: datos,
      layout: "fitColumns",
      placeholder: "<span>No hay ciudadanos registrados en el sistema.</span>",
      columns: [
        { title: "ID", field: "id_usuario", width: 60 },
        {
          title: "Nombre",
          field: "nombre",
          widthGrow: 2,
          formatter: (cell) =>
            cell.getData().activo == 0
              ? `<span style="text-decoration: line-through; color: #888;">${cell.getValue()}</span>`
              : cell.getValue(),
        },
        { title: "Email", field: "email", widthGrow: 2 },
        {
          title: "Saldo",
          field: "saldo_disponible",
          formatter: "money",
          formatterParams: FORMATO_MONEDA,
        },
        {
          title: "Rol",
          field: "rol",
          editor: "list",
          editorParams: {
            values: {
              visitante: "Visitante",
              comprador: "Comprador",
              artista: "Artista",
              admin: "Admin",
            },
          },
          cellEdited: (cell) => cbCambioRol(cell.getData()),
        },
        {
          title: "Acción",
          formatter: (cell) => {
            if (cell.getData().activo == 1) {
              return `<button class="btn-outline" style="padding: 2px 8px; font-size: 0.8rem; color: #dc3545; border-color: #dc3545;">Desterrar</button>`;
            } else {
              return `<button class="btn-outline" style="padding: 2px 8px; font-size: 0.8rem; color: #28a745; border-color: #28a745;">Amnistiar</button>`;
            }
          },
          hozAlign: "center",
          headerSort: false,
          cellClick: (e, cell) => {
            if (cell.getData().activo == 1) cbBorrar(cell.getData().id_usuario);
            else cbAmnistiar(cell.getData().id_usuario);
          },
        },
      ],
    }),

  crearHistorialTransacciones: (id, datos) =>
    new Tabulator(id, {
      data: datos,
      layout: "fitColumns",
      pagination: "local",
      paginationSize: 5,
      placeholder:
        "<span>No se han registrado transacciones o movimientos en el Libro Mayor.</span>",
      columns: [
        { title: "Fecha", field: "fecha", width: 160 },
        {
          title: "Concepto",
          field: "accion",
          width: 180,
          formatter: (cell) => {
            const val = cell.getValue();
            if (val === "INGRESO")
              return '<span class="text-success" style="font-weight: bold;">Depósito</span>';
            if (val === "PUJA")
              return '<span class="text-warning" style="font-weight: bold;">Licitación</span>';
            if (val === "LIBERACION_FONDOS")
              return '<span class="text-info" style="font-weight: bold;">Escrow</span>';
            if (val === "PAGO_LICENCIA")
              return '<span class="text-danger" style="font-weight: bold;">Licencia</span>';
            return val;
          },
        },
        { title: "Detalle de la Operación", field: "detalle", widthGrow: 1 },
      ],
    }),
};
