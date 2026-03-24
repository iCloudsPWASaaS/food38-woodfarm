<template>
    <LoadingComponent :props="loading" />

    <div id="company" class="db-card db-tab-div active">
        <div class="db-card-header">
            <h3 class="db-card-title">Printer & Device Settings</h3>
        </div>
        <div class="db-card-body">
            <div class="form-row">

                <!-- Receipt Printer -->
                <div class="form-col-12 sm:form-col-6">
                    <label class="db-field-title">Receipt Printer</label>
                    <div class="db-device-card" :class="devices.receipt ? 'connected' : ''">
                        <div class="db-device-info" v-if="devices.receipt">
                            <i class="lab lab-printer text-primary"></i>
                            <span class="db-device-name">{{ devices.receipt.label }}</span>
                        </div>
                        <div class="db-device-info text-muted" v-else>
                            <i class="lab lab-printer"></i>
                            <span>No device connected</span>
                        </div>
                        <div class="db-device-actions">
                            <button type="button" class="db-btn text-white bg-primary" @click="connectUsb('receipt')" v-if="!devices.receipt">
                                <i class="lab lab-add"></i>
                                <span>Connect</span>
                            </button>
                            <button type="button" class="db-btn text-white bg-danger" @click="disconnect('receipt')" v-else>
                                <i class="lab lab-close"></i>
                                <span>Disconnect</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Kitchen Printer -->
                <div class="form-col-12 sm:form-col-6">
                    <label class="db-field-title">Kitchen Printer</label>
                    <div class="db-device-card" :class="devices.kitchen ? 'connected' : ''">
                        <div class="db-device-info" v-if="devices.kitchen">
                            <i class="lab lab-printer text-primary"></i>
                            <span class="db-device-name">{{ devices.kitchen.label }}</span>
                        </div>
                        <div class="db-device-info text-muted" v-else>
                            <i class="lab lab-printer"></i>
                            <span>No device connected</span>
                        </div>
                        <div class="db-device-actions">
                            <button type="button" class="db-btn text-white bg-primary" @click="connectUsb('kitchen')" v-if="!devices.kitchen">
                                <i class="lab lab-add"></i>
                                <span>Connect</span>
                            </button>
                            <button type="button" class="db-btn text-white bg-danger" @click="disconnect('kitchen')" v-else>
                                <i class="lab lab-close"></i>
                                <span>Disconnect</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Caller ID -->
                <div class="form-col-12 sm:form-col-6">
                    <label class="db-field-title">Caller ID</label>
                    <div class="db-device-card" :class="devices.caller_id ? 'connected' : ''">
                        <div class="db-device-info" v-if="devices.caller_id">
                            <i class="lab lab-phone text-primary"></i>
                            <span class="db-device-name">{{ devices.caller_id.label }}</span>
                        </div>
                        <div class="db-device-info text-muted" v-else>
                            <i class="lab lab-phone"></i>
                            <span>No device connected</span>
                        </div>
                        <div class="db-device-actions">
                            <button type="button" class="db-btn text-white bg-primary" @click="connectSerial" v-if="!devices.caller_id">
                                <i class="lab lab-add"></i>
                                <span>Connect</span>
                            </button>
                            <button type="button" class="db-btn text-white bg-danger" @click="disconnect('caller_id')" v-else>
                                <i class="lab lab-close"></i>
                                <span>Disconnect</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- API warning -->
                <div class="form-col-12" v-if="apiWarning">
                    <small class="db-field-alert">{{ apiWarning }}</small>
                </div>

            </div>
        </div>
    </div>
</template>

<script>
import LoadingComponent from '../components/LoadingComponent.vue';
import alertService from "../../../services/alertService";

const STORAGE_KEY = "printer_caller_settings";

export default {
    name: "PrinterSettingComponent",
    components: { LoadingComponent },
    data() {
        return {
            loading: { isActive: false },
            apiWarning: "",
            // Each key holds { label, vendorId, productId } or null
            devices: {
                receipt: null,
                kitchen: null,
                caller_id: null,
            },
        };
    },

    mounted() {
        this.loading.isActive = true;
        this.loadFromStorage();
        this.checkApiSupport();
        this.loading.isActive = false;
    },

    methods: {
        /* ─── API support check ──────────────────────────────── */
        checkApiSupport() {
            const warnings = [];
            if (!navigator.usb) {
                warnings.push("WebUSB is not supported in this browser — USB printer connection unavailable.");
            }
            if (!navigator.serial) {
                warnings.push("WebSerial is not supported in this browser — Caller ID COM port unavailable.");
            }
            this.apiWarning = warnings.join(" ");
        },

        /* ─── Connect USB (printer) ──────────────────────────── */
        async connectUsb(target) {
            if (!navigator.usb) {
                alertService.error("WebUSB is not supported in this browser.");
                return;
            }
            try {
                const device = await navigator.usb.requestDevice({ filters: [] });
                const vendorId = device.vendorId?.toString(16).padStart(4, "0") ?? "????";
                const productId = device.productId?.toString(16).padStart(4, "0") ?? "????";
                const label = device.productName || device.manufacturerName || `USB Device (${vendorId}:${productId})`;

                this.devices[target] = { label, vendorId, productId };
                this.saveToStorage();
                alertService.success(`${label} connected.`);
            } catch (e) {
                if (e.name !== "NotFoundError") {
                    alertService.error(e.message || "Could not connect USB device.");
                }
            }
        },

        /* ─── Connect Serial (caller ID) ─────────────────────── */
        async connectSerial() {
            if (!navigator.serial) {
                alertService.error("WebSerial is not supported in this browser.");
                return;
            }
            try {
                const port = await navigator.serial.requestPort({ filters: [] });
                const info = port.getInfo ? port.getInfo() : {};
                const vendorId = info.usbVendorId?.toString(16).padStart(4, "0") ?? "????";
                const productId = info.usbProductId?.toString(16).padStart(4, "0") ?? "????";
                const label = `COM Port (${vendorId}:${productId})`;

                this.devices.caller_id = { label, vendorId, productId };
                this.saveToStorage();
                alertService.success(`${label} connected.`);
            } catch (e) {
                if (e.name !== "NotFoundError") {
                    alertService.error(e.message || "Could not connect serial port.");
                }
            }
        },

        /* ─── Disconnect ─────────────────────────────────────── */
        disconnect(target) {
            this.devices[target] = null;
            this.saveToStorage();
        },

        /* ─── localStorage ───────────────────────────────────── */
        saveToStorage() {
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify({
                    receipt:   this.devices.receipt,
                    kitchen:   this.devices.kitchen,
                    caller_id: this.devices.caller_id,
                }));
            } catch (e) {
                console.warn("Could not save to localStorage:", e);
            }
        },

        loadFromStorage() {
            try {
                const saved = localStorage.getItem(STORAGE_KEY);
                if (saved) {
                    const parsed = JSON.parse(saved);
                    this.devices.receipt   = parsed.receipt   || null;
                    this.devices.kitchen   = parsed.kitchen   || null;
                    this.devices.caller_id = parsed.caller_id || null;
                }
            } catch (e) {
                console.warn("Could not load printer settings from localStorage:", e);
            }
        },
    },
};
</script>