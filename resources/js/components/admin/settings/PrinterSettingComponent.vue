<template>
    <LoadingComponent :props="loading" />

    <div class="db-card db-tab-div active">
        <div class="db-card-header">
            <h3 class="db-card-title">Printer &amp; Device Settings</h3>
        </div>

        <div class="db-card-body">
            <div class="ps-grid">

                <!-- ─── Receipt Printer ─────────────────────────────── -->
                <div class="ps-card" :class="{ 'ps-card--on': printers.receipt.name }">
                    <div class="ps-card__head">
                        <span class="ps-card__label">Receipt Printer</span>
                        <span class="ps-dot" :class="{ 'ps-dot--on': printers.receipt.name }"></span>
                    </div>

                    <div class="ps-type-row">
                        <button
                            v-for="t in types" :key="t.val"
                            class="ps-type-btn"
                            :class="{ 'ps-type-btn--active': printers.receipt.type === t.val }"
                            @click="printers.receipt.type = t.val">
                            {{ t.label }}
                        </button>
                    </div>

                    <div v-if="printers.receipt.name" class="ps-device-name">
                        <i class="lab lab-printer-line"></i>
                        {{ printers.receipt.name }}
                    </div>
                    <div v-else class="ps-device-empty">No device connected</div>

                    <!-- Network fields -->
                    <template v-if="printers.receipt.type === 'network'">
                        <input v-model="printers.receipt.ip"   class="db-field-control ps-input" placeholder="IP Address  e.g. 192.168.1.100" />
                        <input v-model="printers.receipt.port" class="db-field-control ps-input" placeholder="Port  e.g. 9100" type="number" />
                    </template>

                    <div class="ps-actions">
                        <button class="db-btn text-white bg-primary" @click="connect('receipt')">
                            <i class="lab lab-add-circle-line"></i>
                            {{ printers.receipt.name ? 'Change' : 'Connect' }}
                        </button>
                        <button v-if="printers.receipt.name" class="db-btn text-white bg-danger" @click="disconnect('receipt')">
                            <i class="lab lab-close-circle-line"></i> Disconnect
                        </button>
                        <button v-if="printers.receipt.name" class="db-btn text-white" style="background:#6c757d" @click="testPrint('receipt')">
                            <i class="lab lab-printer-line"></i> Test Print
                        </button>
                        <button class="db-btn text-white ps-btn-reset" @click="reset('receipt')">
                            <i class="lab lab-refresh-line"></i> Reset
                        </button>
                    </div>
                </div>

                <!-- ─── Kitchen Printer ─────────────────────────────── -->
                <div class="ps-card" :class="{ 'ps-card--on': printers.kitchen.name }">
                    <div class="ps-card__head">
                        <span class="ps-card__label">Kitchen Printer</span>
                        <span class="ps-dot" :class="{ 'ps-dot--on': printers.kitchen.name }"></span>
                    </div>

                    <div class="ps-type-row">
                        <button
                            v-for="t in types" :key="t.val"
                            class="ps-type-btn"
                            :class="{ 'ps-type-btn--active': printers.kitchen.type === t.val }"
                            @click="printers.kitchen.type = t.val">
                            {{ t.label }}
                        </button>
                    </div>

                    <div v-if="printers.kitchen.name" class="ps-device-name">
                        <i class="lab lab-printer-line"></i>
                        {{ printers.kitchen.name }}
                    </div>
                    <div v-else class="ps-device-empty">No device connected</div>

                    <template v-if="printers.kitchen.type === 'network'">
                        <input v-model="printers.kitchen.ip"   class="db-field-control ps-input" placeholder="IP Address  e.g. 192.168.1.100" />
                        <input v-model="printers.kitchen.port" class="db-field-control ps-input" placeholder="Port  e.g. 9100" type="number" />
                    </template>

                    <div class="ps-actions">
                        <button class="db-btn text-white bg-primary" @click="connect('kitchen')">
                            <i class="lab lab-add-circle-line"></i>
                            {{ printers.kitchen.name ? 'Change' : 'Connect' }}
                        </button>
                        <button v-if="printers.kitchen.name" class="db-btn text-white bg-danger" @click="disconnect('kitchen')">
                            <i class="lab lab-close-circle-line"></i> Disconnect
                        </button>
                        <button v-if="printers.kitchen.name" class="db-btn text-white" style="background:#6c757d" @click="testPrint('kitchen')">
                            <i class="lab lab-printer-line"></i> Test Print
                        </button>
                        <button class="db-btn text-white ps-btn-reset" @click="reset('kitchen')">
                            <i class="lab lab-refresh-line"></i> Reset
                        </button>
                    </div>
                </div>

                <!-- ─── Caller ID ───────────────────────────────────── -->
                <div class="ps-card" :class="{ 'ps-card--on': callerPort }">
                    <div class="ps-card__head">
                        <span class="ps-card__label">Caller ID</span>
                        <span class="ps-dot" :class="{ 'ps-dot--on': callerPort }"></span>
                    </div>

                    <div v-if="callerPort" class="ps-device-name">
                        <i class="lab lab-phone-line"></i>
                        {{ callerPort }}
                    </div>
                    <div v-else class="ps-device-empty">No device connected</div>

                    <input v-model="callerManual" class="db-field-control ps-input" placeholder="Enter port  e.g. COM3" />

                    <div class="ps-actions">
                        <button class="db-btn text-white bg-primary" @click="connectCaller">
                            <i class="lab lab-add-circle-line"></i>
                            {{ callerPort ? 'Change' : 'Connect' }}
                        </button>
                        <button v-if="callerPort" class="db-btn text-white bg-danger" @click="disconnectCaller">
                            <i class="lab lab-close-circle-line"></i> Disconnect
                        </button>
                        <button class="db-btn text-white ps-btn-reset" @click="resetCaller">
                            <i class="lab lab-refresh-line"></i> Reset
                        </button>
                    </div>
                </div>

            </div><!-- /ps-grid -->

            <div v-if="apiWarning" class="db-field-alert mt-3">
                <small>{{ apiWarning }}</small>
            </div>
        </div>
    </div>
</template>

<script>
import LoadingComponent from '../components/LoadingComponent.vue';
import alertService from '../../../services/alertService';

/*
 * localStorage keys & schema
 * ─────────────────────────────────────────────────────────────
 * localStorage.setItem('receipt_printer', JSON.stringify({
 *   type:      'usb' | 'bluetooth' | 'network',
 *   vendorId:  1234  | null,
 *   productId: 5678  | null,
 *   name:      'EPSON TM-T88',
 *   ip:        null  | '192.168.1.100',
 *   port:      9100
 * }))
 *
 * localStorage.setItem('kitchen_printer', JSON.stringify({ ...same shape... }))
 *
 * localStorage.setItem('caller_id_port', 'COM3')
 * ─────────────────────────────────────────────────────────────
 */

function loadPrinter(key) {
    try {
        const raw = localStorage.getItem(key);
        if (!raw) return { type: 'usb', vendorId: null, productId: null, name: '', ip: '', port: '9100' };
        const d = JSON.parse(raw);
        return {
            type:      d.type      || 'usb',
            vendorId:  d.vendorId  ?? null,
            productId: d.productId ?? null,
            name:      d.name      || '',
            ip:        d.ip        || '',
            port:      d.port      ? String(d.port) : '9100',
        };
    } catch { return { type: 'usb', vendorId: null, productId: null, name: '', ip: '', port: '9100' }; }
}

function savePrinter(key, data) {
    try {
        localStorage.setItem(key, JSON.stringify({
            type:      data.type,
            vendorId:  data.vendorId  ?? null,
            productId: data.productId ?? null,
            name:      data.name      || null,
            ip:        data.ip        || null,
            port:      parseInt(data.port, 10) || 9100,
        }));
    } catch (e) { console.warn('Could not save printer:', e); }
}

export default {
    name: 'PrinterSettingComponent',
    components: { LoadingComponent },

    data() {
        return {
            loading: { isActive: false },
            apiWarning: '',

            types: [
                { val: 'usb',       label: 'USB'       },
                { val: 'network',   label: 'Network'   },
                { val: 'bluetooth', label: 'Bluetooth' },
            ],

            printers: {
                receipt: loadPrinter('receipt_printer'),
                kitchen: loadPrinter('kitchen_printer'),
            },

            callerPort:   localStorage.getItem('caller_id_port') || '',
            callerManual: localStorage.getItem('caller_id_port') || '',
        };
    },

    mounted() {
        const w = [];
        if (!navigator.usb)    w.push('WebUSB not supported — USB printers unavailable.');
        if (!navigator.serial) w.push('WebSerial not supported — Caller ID unavailable.');
        this.apiWarning = w.join('  ');
    },

    methods: {

        /* ── Connect (USB / Bluetooth / Network) ─────────────── */
        async connect(role) {
            const p = this.printers[role];
            const storageKey = role === 'receipt' ? 'receipt_printer' : 'kitchen_printer';

            if (p.type === 'network') {
                // Validate IP
                const ip   = (p.ip || '').trim();
                const port = parseInt(p.port, 10) || 9100;
                if (!ip) { alertService.error('Enter an IP address.'); return; }
                const parts = ip.split('.');
                const ok    = parts.length === 4 && parts.every(x => { const n = parseInt(x,10); return !isNaN(n) && n >= 0 && n <= 255; });
                if (!ok) { alertService.error('Invalid IP address.'); return; }

                p.vendorId  = null;
                p.productId = null;
                p.name      = `Network Printer (${ip}:${port})`;
                savePrinter(storageKey, p);
                alertService.success(`${p.name} saved.`);
                return;
            }

            // USB / Bluetooth — WebUSB picker
            if (!navigator.usb) { alertService.error('WebUSB is not supported in this browser.'); return; }
            try {
                const device    = await navigator.usb.requestDevice({ filters: [] });
                p.vendorId      = device.vendorId  ?? null;
                p.productId     = device.productId ?? null;
                p.name          = device.productName
                                  || device.manufacturerName
                                  || `USB ${(p.vendorId||0).toString(16).toUpperCase()}:${(p.productId||0).toString(16).toUpperCase()}`;
                p.ip   = null;
                savePrinter(storageKey, p);
                alertService.success(`"${p.name}" connected.`);
            } catch (e) {
                if (e.name !== 'NotFoundError') alertService.error(e.message || 'Could not connect device.');
            }
        },

        /* ── Disconnect ──────────────────────────────────────── */
        disconnect(role) {
            const storageKey = role === 'receipt' ? 'receipt_printer' : 'kitchen_printer';
            localStorage.removeItem(storageKey);
            this.printers[role] = { type: 'usb', vendorId: null, productId: null, name: '', ip: '', port: '9100' };
        },

        /* ── Caller ID connect ───────────────────────────────── */
        connectCaller() {
            const port = (this.callerManual || '').trim().toUpperCase();
            if (!port) { alertService.error('Enter a port name, e.g. COM3'); return; }
            localStorage.setItem('caller_id_port', port);
            this.callerPort = port;
            alertService.success(`Caller ID saved: ${port}`);
        },

        disconnectCaller() {
            localStorage.removeItem('caller_id_port');
            this.callerPort   = '';
            this.callerManual = '';
        },

        /* ── Reset (clears localStorage + resets state to defaults) ── */
        reset(role) {
            const storageKey = role === 'receipt' ? 'receipt_printer' : 'kitchen_printer';
            localStorage.removeItem(storageKey);
            this.printers[role] = { type: 'usb', vendorId: null, productId: null, name: '', ip: '', port: '9100' };
            alertService.success((role === 'receipt' ? 'Receipt' : 'Kitchen') + ' printer reset.');
        },

        resetCaller() {
            localStorage.removeItem('caller_id_port');
            this.callerPort   = '';
            this.callerManual = '';
            alertService.success('Caller ID reset.');
        },

        /* ── Test Print ──────────────────────────────────────── */
        async testPrint(role) {
            const storageKey = role === 'receipt' ? 'receipt_printer' : 'kitchen_printer';
            let data;
            try { data = JSON.parse(localStorage.getItem(storageKey)); } catch { data = null; }
            if (!data) { alertService.error('No printer saved. Connect first.'); return; }

            const label = role === 'receipt' ? 'RECEIPT PRINTER' : 'KITCHEN PRINTER';

            const enc   = new TextEncoder();
            const line  = '------------------------\n';

            // USB/Bluetooth uses ESC/POS cut: GS V 66 0
            // Network (Star) uses StarPRNT cut: ESC d 2
            const cutUsb     = new Uint8Array([0x1D, 0x56, 0x42, 0x00]); // GS V m n — feed & cut
            const cutNetwork = new Uint8Array([0x1B, 0x64, 0x02]);        // ESC d 2  — Star cut

            const textParts = [
                new Uint8Array([0x1B, 0x40]),           // ESC @ init
                new Uint8Array([0x1B, 0x61, 0x01]),     // center
                new Uint8Array([0x1B, 0x45, 0x01]),     // bold on
                enc.encode('TEST PRINT\n'),
                new Uint8Array([0x1B, 0x45, 0x00]),     // bold off
                enc.encode(line),
                enc.encode(label + '\n'),
                enc.encode(new Date().toLocaleString() + '\n'),
                enc.encode(line),
                enc.encode('Printer is working!\n'),
                new Uint8Array([0x0A, 0x0A, 0x0A]),     // feed 3 lines
            ];

            function mergeBytes(parts) {
                const total  = parts.reduce((s, p) => s + p.length, 0);
                const merged = new Uint8Array(total);
                let off = 0;
                for (const p of parts) { merged.set(p, off); off += p.length; }
                return merged;
            }

            const bytesUsb     = mergeBytes([...textParts, cutUsb]);
            const bytesNetwork = mergeBytes([...textParts, cutNetwork]);

            if (data.type === 'network') {
                try {
                    const res = await fetch('/api/printer', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        },
                        body: JSON.stringify({ ip: data.ip, port: data.port, bytes: Array.from(bytesNetwork) }),
                    });
                    const json = await res.json();
                    if (res.ok) alertService.success('Test print sent to ' + data.ip + ':' + data.port);
                    else        alertService.error(json.message || 'Print failed');
                } catch {
                    alertService.error('Test print request failed.');
                }
                return;
            }

            // USB / Bluetooth
            if (!navigator.usb) { alertService.error('WebUSB not supported.'); return; }
            try {
                const devices = await navigator.usb.getDevices();
                let dev = devices.find(d => d.vendorId === data.vendorId && d.productId === data.productId);
                if (!dev) dev = await navigator.usb.requestDevice({ filters: [] });

                await dev.open();
                if (dev.configuration === null) await dev.selectConfiguration(1);
                await dev.claimInterface(0);

                const iface    = dev.configuration.interfaces[0];
                const endpoint = iface.alternate.endpoints.find(e => e.direction === 'out');
                await dev.transferOut(endpoint.endpointNumber, bytesUsb);
                await dev.releaseInterface(0);
                await dev.close();
                alertService.success('Test print sent!');
            } catch (e) {
                alertService.error('Test print failed: ' + (e.message || e));
            }
        },
    },
};
</script>

<style scoped>
/* ── 3-column grid ─────────────────────────────────────────── */
.ps-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    padding: 4px 0 8px;
}
@media (max-width: 860px) { .ps-grid { grid-template-columns: 1fr 1fr; } }
@media (max-width: 560px) { .ps-grid { grid-template-columns: 1fr; } }

/* ── Card ──────────────────────────────────────────────────── */
.ps-card {
    border: 1.5px solid #E8EAF0;
    border-radius: 12px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    background: #fff;
    transition: border-color .2s;
}
.ps-card--on {
    border-color: #1AB759;
    box-shadow: 0 0 0 3px rgba(26,183,89,.08);
}

/* ── Card head ─────────────────────────────────────────────── */
.ps-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.ps-card__label {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #374151;
}

/* ── Status dot ────────────────────────────────────────────── */
.ps-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #D1D5DB;
    transition: background .3s;
}
.ps-dot--on { background: #1AB759; }

/* ── Type buttons ──────────────────────────────────────────── */
.ps-type-row {
    display: flex;
    gap: 6px;
}
.ps-type-btn {
    flex: 1;
    padding: 5px 0;
    border-radius: 8px;
    border: 1.5px solid #E8EAF0;
    background: transparent;
    font-size: 11px;
    font-weight: 600;
    color: #6B7280;
    cursor: pointer;
    transition: all .15s;
}
.ps-type-btn:hover {
    background: #E5E7EB;
    border-color: #9CA3AF;
    color: #111827;
}
.ps-type-btn--active {
    background: #111827;
    border-color: #111827;
    color: #fff;
}
.ps-type-btn--active:hover {
    background: #374151;
    border-color: #374151;
    color: #fff;
}

/* ── Device name / empty ───────────────────────────────────── */
.ps-device-name {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 600;
    color: #111827;
    padding: 8px 10px;
    background: #F0FDF4;
    border-radius: 8px;
    border: 1px solid #BBF7D0;
}
.ps-device-empty {
    font-size: 12px;
    color: #9CA3AF;
    padding: 8px 10px;
    background: #F9FAFB;
    border-radius: 8px;
    border: 1px dashed #E5E7EB;
}

/* ── Input ─────────────────────────────────────────────────── */
.ps-input {
    width: 100%;
    box-sizing: border-box;
    font-size: 13px;
}

/* ── Action buttons ────────────────────────────────────────── */
.ps-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.ps-actions .db-btn {
    font-size: 12px;
    flex: 1;
    min-width: 80px;
    justify-content: center;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: filter .15s, opacity .15s;
}
.ps-actions .db-btn:hover { filter: brightness(1.12); }
.ps-actions .db-btn:active { filter: brightness(.95); }

.ps-btn-reset {
    background: #F59E0B;
}
.ps-btn-reset:hover {
    background: #D97706;
}

.mt-3 { margin-top: 12px; }
</style>