<template>
    <LoadingComponent :props="loading" />

    <div id="merchant" class="db-card db-tab-div active">
        <div class="db-card-header">
            <h3 class="db-card-title">Merchant Integration Settings</h3>
        </div>
        <div class="db-card-body">
            <div class="form-row">

                <!-- Provider -->
                <div class="form-col-12 sm:form-col-6">
                    <label class="db-field-title required">Provider</label>
                    <select class="db-field-control" v-model="form.provider" @change="onProviderChange">
                        <option value="">— Select Provider —</option>
                        <option v-for="provider in allProviders" :key="provider.value" :value="provider.value">
                            {{ provider.label }} {{ savedConfigs[provider.value] ? '✓' : '' }}
                        </option>
                    </select>
                    <small class="db-field-alert" v-if="errors.provider">{{ errors.provider }}</small>
                </div>

                <!-- Environment -->
                <div class="form-col-12 sm:form-col-6">
                    <label class="db-field-title">Environment</label>
                    <div class="db-field-radio-group">
                        <div class="db-field-radio">
                            <div class="custom-radio">
                                <input :value="false" v-model="form.is_live" id="env_sandbox" type="radio" class="custom-radio-field" />
                                <span class="custom-radio-span"></span>
                            </div>
                            <label for="env_sandbox" class="db-field-label">Sandbox</label>
                        </div>
                        <div class="db-field-radio">
                            <div class="custom-radio">
                                <input :value="true" v-model="form.is_live" id="env_live" type="radio" class="custom-radio-field" />
                                <span class="custom-radio-span"></span>
                            </div>
                            <label for="env_live" class="db-field-label">Live</label>
                        </div>
                    </div>
                </div>

                <template v-if="form.provider">

                    <!-- Client ID -->
                    <div class="form-col-12 sm:form-col-6">
                        <label class="db-field-title required">Client ID</label>
                        <input type="text" class="db-field-control" v-model="form.client_id" placeholder="Enter Client ID" />
                        <small class="db-field-alert" v-if="errors.client_id">{{ errors.client_id }}</small>
                    </div>

                    <!-- Client Secret -->
                    <div class="form-col-12 sm:form-col-6">
                        <label class="db-field-title required">Client Secret</label>
                        <div class="db-field-control-wrap">
                            <input :type="showSecret ? 'text' : 'password'" class="db-field-control" v-model="form.client_secret" placeholder="Enter Client Secret" />
                            <button type="button" class="db-field-eye" @click="showSecret = !showSecret">
                                <i class="lab" :class="showSecret ? 'lab-hide' : 'lab-show'"></i>
                            </button>
                        </div>
                        <small class="db-field-alert" v-if="errors.client_secret">{{ errors.client_secret }}</small>
                    </div>

                    <!-- API Key -->
                    <div class="form-col-12 sm:form-col-6">
                        <label class="db-field-title">API Key</label>
                        <div class="db-field-control-wrap">
                            <input :type="showApiKey ? 'text' : 'password'" class="db-field-control" v-model="form.api_key" placeholder="Enter API Key" />
                            <button type="button" class="db-field-eye" @click="showApiKey = !showApiKey">
                                <i class="lab" :class="showApiKey ? 'lab-hide' : 'lab-show'"></i>
                            </button>
                        </div>
                        <small class="db-field-alert" v-if="errors.api_key">{{ errors.api_key }}</small>
                    </div>

                    <!-- Store ID -->
                    <div class="form-col-12 sm:form-col-6">
                        <label class="db-field-title">Store ID</label>
                        <input type="text" class="db-field-control" v-model="form.store_id" placeholder="Enter Store ID" />
                        <small class="db-field-alert" v-if="errors.store_id">{{ errors.store_id }}</small>
                    </div>

                    <!-- Webhook Secret -->
                    <div class="form-col-12 sm:form-col-6">
                        <label class="db-field-title">Webhook Secret</label>
                        <div class="db-field-control-wrap">
                            <input :type="showWebhook ? 'text' : 'password'" class="db-field-control" v-model="form.webhook_secret" placeholder="Enter Webhook Secret" />
                            <button type="button" class="db-field-eye" @click="showWebhook = !showWebhook">
                                <i class="lab" :class="showWebhook ? 'lab-hide' : 'lab-show'"></i>
                            </button>
                        </div>
                        <small class="db-field-alert" v-if="errors.webhook_secret">{{ errors.webhook_secret }}</small>
                    </div>

                    <!-- Save Button -->
                    <div class="form-col-12" style="padding-left: 16px; padding-bottom: 16px;">
                        <button type="button" class="db-btn text-white bg-primary" @click="save">
                            <i class="lab lab-save"></i>
                            <span>Save</span>
                        </button>
                    </div>

                </template>

            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import LoadingComponent from '../components/LoadingComponent.vue';
import alertService from "../../../services/alertService";

const ALL_PROVIDERS = [
    { value: 'uber_eats', label: 'Uber Eats' },
    { value: 'deliveroo', label: 'Deliveroo' },
    { value: 'just_eat',  label: 'Just Eat'  },
];

const EMPTY_FORM = () => ({
    provider:       '',
    client_id:      '',
    client_secret:  '',
    api_key:        '',
    store_id:       '',
    webhook_secret: '',
    is_live:        false,
});

export default {
    name: 'MerchantSettingComponent',
    components: { LoadingComponent },

    data() {
        return {
            loading:      { isActive: false },
            showSecret:   false,
            showApiKey:   false,
            showWebhook:  false,
            errors:       {},
            allProviders: ALL_PROVIDERS,
            savedConfigs: {},
            form:         EMPTY_FORM(),
        };
    },

    mounted() {
        this.fetchAll();
    },

    methods: {
        fetchAll() {
            this.loading.isActive = true;
            axios.get('admin/setting/merchant')
                .then((res) => {
                    this.savedConfigs = {};
                    (res.data.data ?? res.data).forEach((item) => {
                        this.savedConfigs[item.provider] = item;
                    });
                })
                .catch((err) => {
                    alertService.error(err.message || 'Could not load merchant settings.');
                })
                .finally(() => {
                    this.loading.isActive = false;
                });
        },

        onProviderChange() {
            this.errors      = {};
            this.showSecret  = false;
            this.showApiKey  = false;
            this.showWebhook = false;

            if (this.savedConfigs[this.form.provider]) {
                // Load existing saved data for this provider
                this.form = { ...EMPTY_FORM(), ...this.savedConfigs[this.form.provider] };
            } else {
                // Fresh form for new provider
                this.form = { ...EMPTY_FORM(), provider: this.form.provider };
            }
        },

        validate() {
            this.errors = {};
            if (!this.form.provider)      this.errors.provider      = 'Provider is required.';
            if (!this.form.client_id)     this.errors.client_id     = 'Client ID is required.';
            if (!this.form.client_secret) this.errors.client_secret = 'Client Secret is required.';
            return Object.keys(this.errors).length === 0;
        },

        save() {
            if (!this.validate()) {
                alertService.error('Please fill in the required fields.');
                return;
            }
            this.loading.isActive = true;
            axios.put('admin/setting/merchant', this.form)
                .then((res) => {
                    (res.data.data ?? res.data).forEach((item) => {
                        this.savedConfigs[item.provider] = item;
                    });
                    alertService.success('Merchant settings saved successfully.');
                })
                .catch((err) => {
                    if (err.response?.data?.errors) {
                        this.errors = err.response.data.errors;
                    }
                    alertService.error(err.response?.data?.message || 'Could not save merchant settings.');
                })
                .finally(() => {
                    this.loading.isActive = false;
                });
        },
    },
};
</script>