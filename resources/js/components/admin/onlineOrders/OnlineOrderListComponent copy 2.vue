<template>
    <LoadingComponent :props="loading" />
    <div class="col-12">
        <div class="db-card">
            <div class="db-card-header border-none">
                <h3 class="db-card-title">{{ $t('menu.online_orders') }}</h3>
                <div class="db-card-filter">
                    <TableLimitComponent :method="list" :search="props.search" :page="paginationPage" />
                    <FilterComponent @click.prevent="handleSlide('online-order-filter')" />
                    <div class="dropdown-group">
                        <ExportComponent />
                        <div
                            class="dropdown-list db-card-filter-dropdown-list transition-all duration-300 scale-y-0 origin-top">
                            <PrintComponent :props="printObj" />
                            <ExcelComponent :method="xls" />
                            <PdfComponent :method="pdf" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-filter-div" id="online-order-filter">
                <form class="p-4 sm:p-5 mb-5" @submit.prevent="search">
                    <div class="row">
                        <div class="col-12 sm:col-6 md:col-4 xl:col-3">
                            <label for="order_id" class="db-field-title after:hidden">{{ $t('label.order_id') }}</label>
                            <input id="order_id" v-model="props.search.order_serial_no" type="text"
                                class="db-field-control">
                        </div>

                        <div class="col-12 sm:col-6 md:col-4 xl:col-3">
                            <label for="searchStatus" class="db-field-title after:hidden">{{
                                $t('label.status')
                                }}</label>
                            <vue-select class="db-field-control f-b-custom-select" id="searchStatus"
                                v-model="props.search.status" :options="[
                                    { id: enums.orderStatusEnum.PENDING, name: $t('label.pending') },
                                    { id: enums.orderStatusEnum.ACCEPT, name: $t('label.accept') },
                                    { id: enums.orderStatusEnum.PREPARING, name: $t('label.preparing') },
                                    { id: enums.orderStatusEnum.OUT_FOR_DELIVERY, name: $t('label.out_for_delivery') },
                                    { id: enums.orderStatusEnum.DELIVERED, name: $t('label.delivered') },
                                    { id: enums.orderStatusEnum.CANCELED, name: $t('label.canceled') },
                                    { id: enums.orderStatusEnum.REJECTED, name: $t('label.rejected') },
                                    { id: enums.orderStatusEnum.RETURNED, name: $t('label.returned') }
                                ]" label-by="name" value-by="id" :closeOnSelect="true" :searchable="true"
                                :clearOnClose="true" placeholder="--" search-placeholder="--" />
                        </div>

                        <div class="col-12 sm:col-6 md:col-4 xl:col-3">
                            <label for="user_id" class="db-field-title">
                                {{ $t("label.customer") }}
                            </label>
                            <vue-select class="db-field-control f-b-custom-select" id="user_id"
                                v-model="props.search.user_id" :options="customers" label-by="name" value-by="id"
                                :closeOnSelect="true" :searchable="true" :clearOnClose="true" placeholder="--"
                                search-placeholder="--" />
                        </div>

                        <div class="col-12 sm:col-6 md:col-4 xl:col-3">
                            <label for="searchStartDate" class="db-field-title after:hidden">
                                {{ $t('label.date') }}
                            </label>
                            <Datepicker hideInputIcon autoApply :enableTimePicker="false" utc="false"
                                @update:modelValue="handleDate" v-model="props.form.date" range
                                :preset-ranges="presetRanges">
                                <template #yearly="{ label, range, presetDateRange }">
                                    <span @click="presetDateRange(range)">{{ label }}</span>
                                </template>
                            </Datepicker>
                        </div>

                        <div class="col-12">
                            <div class="flex flex-wrap gap-3 mt-4">
                                <button class="db-btn py-2 text-white bg-primary">
                                    <i class="lab lab-search-line lab-font-size-16"></i>
                                    <span>{{ $t('button.search') }}</span>
                                </button>
                                <button class="db-btn py-2 text-white bg-gray-600" @click="clear">
                                    <i class="lab lab-cross-line-2 lab-font-size-22"></i>
                                    <span>{{ $t('button.clear') }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="db-table-responsive">
                <table class="db-table stripe" id="print" :dir="direction">
                    <thead class="db-table-head">
                        <tr class="db-table-head-tr">
                            <th class="db-table-head-th">{{ $t('label.order_id') }}</th>
                            <th class="db-table-head-th">{{ $t('label.order_type') }}</th>
                            <th class="db-table-head-th">{{ $t('label.customer') }}</th>
                            <th class="db-table-head-th">{{ $t('label.amount') }}</th>
                            <th class="db-table-head-th">{{ $t('label.date') }}</th>
                            <th class="db-table-head-th">{{ $t('label.status') }}</th>
                            <th class="db-table-head-th hidden-print" v-if="permissionChecker('online-orders')">
                                {{ $t('label.action') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="db-table-body" v-if="orders.length > 0">
                        <tr class="db-table-body-tr" v-for="order in orders" :key="order">
                            <td class="db-table-body-td">
                                {{ order.order_serial_no }}

                            </td>
                            <td class="db-table-body-td">
                                <span :class="statusClass(order.order_type)">
                                    {{ enums.orderTypeEnumArray[order.order_type] }}
                                </span>
                            </td>

                            <td class="db-table-body-td">
                                {{ textShortener(order.customer_name, 20) }}
                            </td>
                            <td class="db-table-body-td">{{ order.total_amount_price }}</td>
                            <td class="db-table-body-td">
                                {{ order.order_datetime }}
                            </td>
                            <td class="db-table-body-td">
                                <span :class="orderStatusClass(order.status)">
                                    {{ enums.orderStatusEnumArray[order.status] }}
                                </span>
                                <span :class="orderStatusClass(order.is_advance_order)"
                                    v-if="order.is_advance_order === enums.isAdvanceOrderEnum.YES">
                                    {{ $t('label.advance') }}
                                </span>
                            </td>
                            <!-- <td class="db-table-body-td hidden-print" v-if="permissionChecker('online-orders')">
                                <div class="flex justify-start items-center sm:items-start sm:justify-start gap-1.5">
                                    <SmIconViewComponent :link="'admin.order.show'" :id="order.id"
                                        v-if="permissionChecker('online-orders')" />
                                </div>
                            </td> --> <!-- extra -->
                            <td class="db-table-body-td hidden-print" v-if="permissionChecker('online-orders')">
                                <div class="flex justify-start items-center sm:items-start sm:justify-start gap-1.5">
                                    <SmIconViewComponent :link="'admin.order.show'" :id="order.id"
                                        v-if="permissionChecker('online-orders')" />
                                
                                    <div class="flex flex-wrap gap-3" v-if="order.status === enums.orderStatusEnum.PENDING">
                                        <OnlineOrderReasonComponent :orderId="order.id" :modalId="'reasonModal_' + order.id" />
                                        <button type="button" @click="changeStatus(enums.orderStatusEnum.ACCEPT, order.id)"
                                            class="flex items-center justify-center text-white gap-2 px-4 h-[38px] rounded shadow-db-card bg-[#2AC769]">
                                            <i class="lab lab-save"></i>
                                            <span class="text-sm capitalize text-white">{{ $t('button.accept') }}</span>
                                        </button>

                                        <button type="button" @click="showOrder(order.id)" 
                                            class="flex items-center justify-center text-white gap-2 px-4 h-[38px] rounded shadow-db-card bg-[#2AC769]">
                                            <i class="lab lab-printer-line lab-font-size-16 text-white"></i> Print
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                    <tbody class="db-table-body" v-else>
                        <tr class="db-table-body-tr">
                            <td class="db-table-body-td text-center" colspan="7">
                                <div class="p-4">
                                    <div class="max-w-[300px] mx-auto mt-2">
                                        <img class="w-full h-full" :src="ENV.API_URL + '/images/default/not-found.png'"
                                            alt="Not Found">
                                    </div>
                                    <span class="d-block mt-3 text-lg">{{ $t('message.no_data_available') }}</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-6"
                v-if="orders.length > 0">
                <PaginationSMBox :pagination="pagination" :method="list" />
                <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                    <PaginationTextComponent :props="{ page: paginationPage }" />
                    <PaginationBox :pagination="pagination" :method="list" />
                </div>
            </div>
        </div>
    </div>

    <!-- extra -->
    <!-- Hidden receipt target for WebUSB printing -->
    <div style="position:fixed; left:-9999px; top:0; z-index:-1;">
        <div id="usb-receipt-target">
            <PosOrderReceiptListComponent :order="$store.getters['posOrder/show']" />
        </div>
    </div>
    
</template>
<script>
import LoadingComponent from "../components/LoadingComponent";
import alertService from "../../../services/alertService";
import PaginationTextComponent from "../components/pagination/PaginationTextComponent";
import PaginationBox from "../components/pagination/PaginationBox";
import PaginationSMBox from "../components/pagination/PaginationSMBox";
import appService from "../../../services/appService";
import orderStatusEnum from "../../../enums/modules/orderStatusEnum";
import orderTypeEnum from "../../../enums/modules/orderTypeEnum";
import TableLimitComponent from "../components/TableLimitComponent";
import SmIconDeleteComponent from "../components/buttons/SmIconDeleteComponent";
import SmIconViewComponent from "../components/buttons/SmIconViewComponent";
import FilterComponent from "../components/buttons/collapse/FilterComponent";
import ExportComponent from "../components/buttons/export/ExportComponent";
import PrintComponent from "../components/buttons/export/PrintComponent";
import ExcelComponent from "../components/buttons/export/ExcelComponent";
import PdfComponent from "../components/buttons/export/PdfComponent";
import Datepicker from "@vuepic/vue-datepicker";
import "@vuepic/vue-datepicker/dist/main.css";
import { ref } from 'vue';
import { endOfMonth, endOfYear, startOfMonth, startOfYear, subMonths } from 'date-fns';
import statusEnum from "../../../enums/modules/statusEnum";
import isAdvanceOrderEnum from "../../../enums/modules/isAdvanceOrderEnum";
import displayModeEnum from "../../../enums/modules/displayModeEnum";
import SourceEnum from "../../../enums/modules/sourceEnum";
import ENV from "../../../config/env";

//extra
import print from "vue3-print-nb"; // print directive
import PosOrderReceiptListComponent from "../posOrders/PosOrderReceiptListComponent"; // receipt
import OnlineOrderReasonComponent from "./OnlineOrderReasonComponent"; // reject modal

export default {
    name: "OnlineOrderListComponent",
    components: {
        TableLimitComponent,
        PaginationSMBox,
        PaginationBox,
        PaginationTextComponent,
        LoadingComponent,
        SmIconDeleteComponent,
        SmIconViewComponent,
        FilterComponent,
        ExportComponent,
        PrintComponent,
        ExcelComponent,
        PdfComponent,
        Datepicker,

        //extra
        PosOrderReceiptListComponent,   
        OnlineOrderReasonComponent  
    },
    directives: { //extra
        print
    },
    setup() {
        const date = ref();

        const presetRanges = ref([
            { label: 'Today', range: [new Date(), new Date()] },
            { label: 'This month', range: [startOfMonth(new Date()), endOfMonth(new Date())] },
            {
                label: 'Last month',
                range: [startOfMonth(subMonths(new Date(), 1)), endOfMonth(subMonths(new Date(), 1))],
            },
            { label: 'This year', range: [startOfYear(new Date()), endOfYear(new Date())] },
            {
                label: 'This year (slot)',
                range: [startOfYear(new Date()), endOfYear(new Date())],
                slot: 'yearly',
            },
        ]);

        return {
            date,
            presetRanges,
        }
    },
    data() {
        return {
            loading: {
                isActive: false
            },
            enums: {
                orderStatusEnum: orderStatusEnum,
                orderTypeEnum: orderTypeEnum,
                isAdvanceOrderEnum: isAdvanceOrderEnum,
                orderStatusEnumArray: {
                    [orderStatusEnum.PENDING]: this.$t("label.pending"),
                    [orderStatusEnum.ACCEPT]: this.$t("label.accept"),
                    [orderStatusEnum.PREPARING]: this.$t("label.preparing"),
                    [orderStatusEnum.PREPARED]: this.$t("label.prepared"),
                    [orderStatusEnum.OUT_FOR_DELIVERY]: this.$t("label.out_for_delivery"),
                    [orderStatusEnum.DELIVERED]: this.$t("label.delivered"),
                    [orderStatusEnum.CANCELED]: this.$t("label.canceled"),
                    [orderStatusEnum.REJECTED]: this.$t("label.rejected"),
                    [orderStatusEnum.RETURNED]: this.$t("label.returned")
                },
                orderTypeEnumArray: {
                    [orderTypeEnum.DELIVERY]: this.$t("label.delivery"),
                    [orderTypeEnum.TAKEAWAY]: this.$t("label.takeaway")
                }
            },
            printLoading: true,
            printObj: {
                id: "print",
                popTitle: this.$t("menu.online_orders"),
            },
            props: {
                form: {
                    date: null,
                },
                search: {
                    paginate: 1,
                    page: 1,
                    per_page: 10,
                    order_column: 'id',
                    order_by: "desc",
                    order_serial_no: "",
                    user_id: null,
                    excepts: orderTypeEnum.DINING_TABLE,
                    exceptSource: SourceEnum.POS,
                    status: null,
                    from_date: "",
                    to_date: "",
                }
            },
            ENV: ENV,

            //extra
            payment_status: null,
            order_status: null,
            delivery_boy: null,

            // WebUSB
            usbDevice: null,
            printerConnected: false,
            PRINTER_STORAGE_KEY: 'webusb_printer_device',
            //PRINTER_NAME: 'Microsoft Print to PDF', // default fallback
        }
    },
    mounted() {
        this.list();
        this.$store.dispatch('user/lists', {
            order_column: 'id',
            order_type: 'asc',
            status: statusEnum.ACTIVE
        });

        this.autoConnectPrinter(); //extra print
    },
    computed: {
        orders: function () {
            return this.$store.getters['onlineOrder/lists'];
        },
        customers: function () {
            return this.$store.getters['user/lists'];
        },
        pagination: function () {
            return this.$store.getters['onlineOrder/pagination'];
        },
        paginationPage: function () {
            return this.$store.getters['onlineOrder/page'];
        },
        direction: function () {
            return this.$store.getters['frontendLanguage/show'].display_mode === displayModeEnum.RTL ? 'rtl' : 'ltr';
        },
    },
    methods: {
        permissionChecker(e) {
            return appService.permissionChecker(e);
        },
        statusClass: function (status) {
            return appService.statusClass(status);
        },
        orderStatusClass: function (status) {
            return appService.orderStatusClass(status);
        },
        textShortener: function (text, number = 30) {
            return appService.textShortener(text, number);
        },
        handleSlide: function (id) {
            return appService.handleSlide(id);
        },
        search: function () {
            this.list();
        },
        handleDate: function (e) {
            if (e) {
                this.props.search.from_date = e[0];
                this.props.search.to_date = e[1];
            } else {
                this.props.form.date = null;
                this.props.search.from_date = null;
                this.props.search.to_date = null;
            }
        },
        clear: function () {
            this.props.search.paginate = 1;
            this.props.search.page = 1;
            this.props.search.order_by = "desc";
            this.props.search.order_serial_no = "";
            this.props.search.status = null;
            this.props.search.excepts = orderTypeEnum.POS + '|' + orderTypeEnum.DINING_TABLE;
            this.props.search.from_date = "";
            this.props.search.to_date = "";
            this.props.search.user_id = null;
            this.props.form.date = null;
            this.list();
        },
        list: function (page = 1) {
            this.loading.isActive = true;
            this.props.search.page = page;
            this.$store.dispatch('onlineOrder/lists', this.props.search).then(res => {
                this.loading.isActive = false;
            }).catch((err) => {
                this.loading.isActive = false;
            });
        },
        xls: function () {
            this.loading.isActive = true;
            this.$store.dispatch("onlineOrder/export", this.props.search).then((res) => {
                this.loading.isActive = false;
                const blob = new Blob([res.data], {
                    type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
                });
                const link = document.createElement("a");
                link.href = URL.createObjectURL(blob);
                link.download = this.$t("menu.online_orders");
                link.click();
                URL.revokeObjectURL(link.href);
            }).catch((err) => {
                this.loading.isActive = false;
                alertService.error(err.response.data.message);
            });
        },
        pdf: function () {
            this.loading.isActive = true;
            this.$store.dispatch("onlineOrder/pdf", this.props.search).then((res) => {
                this.loading.isActive = false;
                const blob = new Blob([res.data]);
                const link = document.createElement("a");
                link.href = URL.createObjectURL(blob);
                link.download = this.$t("menu.online_orders") + ".pdf";
                link.click();
                URL.revokeObjectURL(link.href);
            }).catch((err) => {
                this.loading.isActive = false;
                alertService.error(err.response.data.message);
            });
        },

        //extra
        showOrder: async function (orderId) {
            this.loading.isActive = true;
            try {
                await this.$store.dispatch('posOrder/show', orderId);
                this.loading.isActive = false;
                await this.$nextTick();

                if (this.usbDevice) {
                    await this.printViaUSB();
                } else {
                    const receiptEl = document.getElementById('receipt-content');
                    if (!receiptEl) return;

                    const win = window.open('', '_blank', 'width=420,height=700');
                    win.document.write(`
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <title>Receipt</title>
                            <style>
                                * { box-sizing: border-box; margin: 0; padding: 0; }
                                body {
                                    font-family: monospace;
                                    background: white;
                                    display: flex;
                                    justify-content: center;
                                    padding: 10px;
                                }
                                table { width: 100%; border-collapse: collapse; }
                                @media print {
                                    body { padding: 0; }
                                    @page { margin: 5mm; size: 80mm auto; }
                                }
                            </style>
                        </head>
                        <body>
                            ${receiptEl.outerHTML}
                        </body>
                        </html>
                    `);
                    win.document.close();
                    win.focus();
                    setTimeout(() => {
                        win.print();
                        win.close();
                    }, 300);
                }
            } catch (err) {
                this.loading.isActive = false;
                console.error(err);
            }
        },

        // Auto connect on mount - uses localStorage or default printer name
        autoConnectPrinter: async function () {
            try {
                const saved = JSON.parse(localStorage.getItem(this.PRINTER_STORAGE_KEY));

                // No saved device = use default (window.print), nothing to connect
                if (!saved) {
                    console.log('No saved printer, will use default print dialog');
                    return;
                }

                // Saved device = try to connect via WebUSB
                const devices = await navigator.usb.getDevices();
                const device = devices.find(d =>
                    d.vendorId === saved.vendorId && d.productId === saved.productId
                );

                if (device) {
                    await device.open();
                    await device.selectConfiguration(1);
                    await device.claimInterface(0);
                    this.usbDevice = device;
                    this.printerConnected = true;
                    console.log('Connected to:', device.productName);
                }
            } catch (err) {
                console.error('Auto-connect failed:', err);
            }
        },

        // Capture receipt div and send to USB printer
        printViaUSB: async function () {
            try {
                const receiptEl = document.getElementById('usb-receipt-target');
                if (!receiptEl) {
                    alertService.error('Receipt element not found');
                    return;
                }

                const html2canvas = (await import('html2canvas')).default;
                const canvas = await html2canvas(receiptEl, {
                    scale: 2,
                    backgroundColor: '#ffffff',
                    useCORS: true
                });

                const ctx = canvas.getContext('2d');
                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);

                const escposData = this.convertImageToESCPOS(imageData);

                const encoder = new TextEncoder();
                const init = encoder.encode('\x1B\x40');      // Initialize printer
                const cut = encoder.encode('\x1D\x56\x00');  // Cut paper

                await this.usbDevice.transferOut(1, init);
                await this.usbDevice.transferOut(1, escposData);
                await this.usbDevice.transferOut(1, cut);

                alertService.success('Receipt printed!');
            } catch (err) {
                console.error('Printing failed:', err);
                alertService.error('Printing failed: ' + err.message);
            }
        },

        // Convert canvas pixels to ESC/POS byte format
        convertImageToESCPOS: function (imageData) {
            const { width, height, data } = imageData;
            const bytes = [];

            for (let y = 0; y < height; y += 8) {
                bytes.push(0x1B, 0x2A, 0x21, width & 0xFF, (width >> 8) & 0xFF);

                for (let x = 0; x < width; x++) {
                    let byte = 0;
                    for (let bit = 0; bit < 8; bit++) {
                        const pixelY = y + bit;
                        if (pixelY >= height) continue;
                        const offset = (pixelY * width + x) * 4;
                        const r = data[offset];
                        const g = data[offset + 1];
                        const b = data[offset + 2];
                        const avg = (r + g + b) / 3;
                        if (avg < 128) byte |= 1 << (7 - bit);
                    }
                    bytes.push(byte);
                }
                bytes.push(0x0A);
            }

            return new Uint8Array(bytes);
        },

        changeStatus: function (status, orderId) {
            appService.acceptOrder().then((res) => {
                try {
                    this.loading.isActive = true;
                    this.$store.dispatch("onlineOrder/changeStatus", {
                        id: orderId,
                        status: status,
                    }).then((res) => {
                        this.order_status = res.data.data.status;
                        this.loading.isActive = false;
                        alertService.successFlip(
                            1,
                            this.$t("label.status")
                        );
                    }).catch((err) => {
                        this.loading.isActive = false;
                        alertService.error(err.response.data.message);
                    });
                } catch (err) {
                    this.loading.isActive = false;
                    alertService.error(err.response.data.message);
                }
            }).catch((err) => {
                this.loading.isActive = false;
            });
        }
    }
}
</script>

<style scoped>
@media print {
    .hidden-print {
        display: none !important;
    }
}
</style>