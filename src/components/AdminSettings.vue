<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { showSuccess, showError } from '@nextcloud/dialogs';
import { t } from '@nextcloud/l10n';
import { generateUrl } from '@nextcloud/router';
import axios from '@nextcloud/axios';

interface AdminSettings {
  botUrl: string;
  botToken: string;
  allowedChannels: string[];
  enableAI: boolean;
  aiModel: string;
  maxMessages: number;
  // OpenAI Settings (US-007)
  openaiEnabled: boolean;
  openaiApiKey: string;
  openaiModel: string;
  // Custom Provider Settings (US-008)
  customProviderEnabled: boolean;
  customProviderEndpoint: string;
  customProviderModel: string;
  customProviderHeaders: Record<string, string>;
  // AI Status (US-010)
  activeProvider: string;
  aiStatus: string;
  lastError: string;
}

// Get initial state from server-side
const getInitialState = (): AdminSettings => {
  // @ts-ignore - provided by Nextcloud
  return (window as any)._talk_bot_settings || {
    botUrl: '',
    botToken: '',
    allowedChannels: [],
    enableAI: false,
    aiModel: 'claude-3-opus',
    maxMessages: 50,
    openaiEnabled: false,
    openaiApiKey: '',
    openaiModel: 'gpt-4',
    customProviderEnabled: false,
    customProviderEndpoint: '',
    customProviderModel: '',
    customProviderHeaders: {},
    activeProvider: 'none',
    aiStatus: 'inactive',
    lastError: '',
  };
};

const settings = ref<AdminSettings>(getInitialState());

const loading = ref(false);
const saving = ref(false);
const testingConnection = ref(false);
const activeTab = ref<'general' | 'openai' | 'custom' | 'status'>('general');

// OpenAI Models (US-007)
const openaiModels = [
  { value: 'gpt-4', label: 'GPT-4' },
  { value: 'gpt-4-turbo', label: 'GPT-4 Turbo' },
  { value: 'gpt-4o', label: 'GPT-4o' },
  { value: 'gpt-3.5-turbo', label: 'GPT-3.5 Turbo' },
];

const channelInput = ref('');
const headerKeyInput = ref('');
const headerValueInput = ref('');

// Computed status color (US-010)
const statusColor = computed(() => {
  switch (settings.value.aiStatus) {
    case 'active':
      return 'green';
    case 'error':
      return 'red';
    case 'testing':
      return 'yellow';
    default:
      return 'gray';
  }
});

const statusLabel = computed(() => {
  switch (settings.value.aiStatus) {
    case 'active':
      return t('talk_bot', 'Active');
    case 'error':
      return t('talk_bot', 'Error');
    case 'testing':
      return t('talk_bot', 'Testing');
    default:
      return t('talk_bot', 'Inactive');
  }
});

const addChannel = () => {
  const channel = channelInput.value.trim();
  if (channel && !settings.value.allowedChannels.includes(channel)) {
    settings.value.allowedChannels.push(channel);
    channelInput.value = '';
  }
};

const removeChannel = (index: number) => {
  settings.value.allowedChannels.splice(index, 1);
};

const addHeader = () => {
  const key = headerKeyInput.value.trim();
  const value = headerValueInput.value.trim();
  if (key && value) {
    settings.value.customProviderHeaders[key] = value;
    headerKeyInput.value = '';
    headerValueInput.value = '';
  }
};

const removeHeader = (key: string) => {
  delete settings.value.customProviderHeaders[key];
};

const saveSettings = async () => {
  saving.value = true;
  try {
    await axios.post(generateUrl('/apps/talk_bot/settings/admin'), {
      botUrl: settings.value.botUrl,
      botToken: settings.value.botToken,
      allowedChannels: settings.value.allowedChannels,
      enableAI: settings.value.enableAI,
      aiModel: settings.value.aiModel,
      maxMessages: settings.value.maxMessages,
      // OpenAI Settings (US-007)
      openaiEnabled: settings.value.openaiEnabled ? 'yes' : 'no',
      openaiApiKey: settings.value.openaiApiKey,
      openaiModel: settings.value.openaiModel,
      // Custom Provider Settings (US-008)
      customProviderEnabled: settings.value.customProviderEnabled ? 'yes' : 'no',
      customProviderEndpoint: settings.value.customProviderEndpoint,
      customProviderModel: settings.value.customProviderModel,
      customProviderHeaders: JSON.stringify(settings.value.customProviderHeaders),
    });
    showSuccess(t('talk_bot', 'Settings saved successfully'));
  } catch (error) {
    console.error('Failed to save settings:', error);
    showError(t('talk_bot', 'Failed to save settings'));
  } finally {
    saving.value = false;
  }
};

// Test OpenAI connection (US-007, US-010)
const testOpenAIConnection = async () => {
  testingConnection.value = true;
  settings.value.aiStatus = 'testing';
  try {
    const response = await axios.post(generateUrl('/apps/talk_bot/settings/test-ai'), {
      provider: 'openai',
      config: {
        apiKey: settings.value.openaiApiKey,
        model: settings.value.openaiModel,
      },
    });
    if (response.data.status === 'success') {
      settings.value.aiStatus = 'active';
      settings.value.activeProvider = 'openai';
      showSuccess(t('talk_bot', 'OpenAI connection successful'));
    } else {
      settings.value.aiStatus = 'error';
      settings.value.lastError = response.data.message;
      showError(t('talk_bot', 'Connection failed: ') + response.data.message);
    }
  } catch (error: any) {
    settings.value.aiStatus = 'error';
    settings.value.lastError = error.response?.data?.message || 'Connection failed';
    showError(t('talk_bot', 'Connection failed'));
  } finally {
    testingConnection.value = false;
  }
};

// Test Custom Provider connection (US-008, US-010)
const testCustomProviderConnection = async () => {
  testingConnection.value = true;
  settings.value.aiStatus = 'testing';
  try {
    const response = await axios.post(generateUrl('/apps/talk_bot/settings/test-ai'), {
      provider: 'custom',
      config: {
        endpoint: settings.value.customProviderEndpoint,
        model: settings.value.customProviderModel,
        headers: settings.value.customProviderHeaders,
      },
    });
    if (response.data.status === 'success') {
      settings.value.aiStatus = 'active';
      settings.value.activeProvider = 'custom';
      showSuccess(t('talk_bot', 'Custom provider connection successful'));
    } else {
      settings.value.aiStatus = 'error';
      settings.value.lastError = response.data.message;
      showError(t('talk_bot', 'Connection failed: ') + response.data.message);
    }
  } catch (error: any) {
    settings.value.aiStatus = 'error';
    settings.value.lastError = error.response?.data?.message || 'Connection failed';
    showError(t('talk_bot', 'Connection failed'));
  } finally {
    testingConnection.value = false;
  }
};

// Quick test button (US-010)
const quickTest = async () => {
  if (settings.value.activeProvider === 'none') {
    showError(t('talk_bot', 'No active provider selected'));
    return;
  }
  if (settings.value.activeProvider === 'openai') {
    await testOpenAIConnection();
  } else if (settings.value.activeProvider === 'custom') {
    await testCustomProviderConnection();
  }
};

// Refresh status
const refreshStatus = async () => {
  try {
    const response = await axios.get(generateUrl('/apps/talk_bot/settings/ai-status'));
    if (response.data.status === 'success') {
      settings.value.aiStatus = response.data.data.aiStatus;
      settings.value.activeProvider = response.data.data.activeProvider;
      settings.value.lastError = response.data.data.lastError;
    }
  } catch (error) {
    console.error('Failed to refresh status:', error);
  }
};

onMounted(() => {
  refreshStatus();
});
</script>

<template>
  <div class="talk-bot-admin">
    <h2>{{ t('talk_bot', 'Talk Bot Settings') }}</h2>

    <!-- Tab Navigation -->
    <div class="tabs">
      <button
        :class="['tab', { active: activeTab === 'general' }]"
        @click="activeTab = 'general'"
      >
        {{ t('talk_bot', 'General') }}
      </button>
      <button
        :class="['tab', { active: activeTab === 'openai' }]"
        @click="activeTab = 'openai'"
      >
        {{ t('talk_bot', 'OpenAI') }}
      </button>
      <button
        :class="['tab', { active: activeTab === 'custom' }]"
        @click="activeTab = 'custom'"
      >
        {{ t('talk_bot', 'Custom Provider') }}
      </button>
      <button
        :class="['tab', { active: activeTab === 'status' }]"
        @click="activeTab = 'status'"
      >
        {{ t('talk_bot', 'AI Status') }}
      </button>
    </div>

    <!-- General Settings Tab -->
    <div v-show="activeTab === 'general'" class="tab-content">
      <NcCard>
        <h3>{{ t('talk_bot', 'Bot Configuration') }}</h3>

        <NcTextField
          v-model="settings.botUrl"
          :label="t('talk_bot', 'Bot URL')"
          :placeholder="t('talk_bot', 'https://api.example.com')"
        />

        <NcTextField
          v-model="settings.botToken"
          :label="t('talk_bot', 'Bot Token')"
          :placeholder="t('talk_bot', 'Enter bot token')"
          type="password"
        />
      </NcCard>

      <NcCard>
        <h3>{{ t('talk_bot', 'Allowed Channels') }}</h3>
        <p class="description">
          {{ t('talk_bot', 'Specify which channels the bot should respond to') }}
        </p>

        <div class="channel-input">
          <NcTextField
            v-model="channelInput"
            :label="t('talk_bot', 'Add Channel')"
            :placeholder="t('talk_bot', 'channel-name')"
            @keyup.enter="addChannel"
          />
          <NcButton
            type="secondary"
            :disabled="!channelInput.trim()"
            @click="addChannel"
          >
            {{ t('talk_bot', 'Add') }}
          </NcButton>
        </div>

        <div v-if="settings.allowedChannels.length > 0" class="channel-list">
          <NcListItem
            v-for="(channel, index) in settings.allowedChannels"
            :key="index"
            :name="channel"
          >
            <template #subname>
              {{ t('talk_bot', 'Channel') }}
            </template>
            <template #actions>
              <NcActionButton @click="removeChannel(index)">
                {{ t('talk_bot', 'Remove') }}
              </NcActionButton>
            </template>
          </NcListItem>
        </div>
      </NcCard>

      <NcCard>
        <h3>{{ t('talk_bot', 'AI Settings') }}</h3>

        <NcCheckboxRadioSwitch
          v-model="settings.enableAI"
          type="switch"
        >
          {{ t('talk_bot', 'Enable AI responses') }}
        </NcCheckboxRadioSwitch>

        <template v-if="settings.enableAI">
          <NcSelect
            v-model="settings.aiModel"
            :options="[
              { value: 'claude-3-opus', label: 'Claude 3 Opus' },
              { value: 'claude-3-sonnet', label: 'Claude 3 Sonnet' },
              { value: 'gpt-4-turbo', label: 'GPT-4 Turbo' },
              { value: 'gpt-3.5-turbo', label: 'GPT-3.5 Turbo' },
            ]"
            :label="t('talk_bot', 'AI Model')"
            :placeholder="t('talk_bot', 'Select AI model')"
          />

          <NcNumberInput
            v-model="settings.maxMessages"
            :label="t('talk_bot', 'Max Messages to Remember')"
            :min="10"
            :max="200"
          />
        </template>
      </NcCard>
    </div>

    <!-- OpenAI Settings Tab (US-007) -->
    <div v-show="activeTab === 'openai'" class="tab-content">
      <NcCard>
        <h3>{{ t('talk_bot', 'OpenAI Configuration') }}</h3>

        <NcCheckboxRadioSwitch
          v-model="settings.openaiEnabled"
          type="switch"
        >
          {{ t('talk_bot', 'Enable OpenAI') }}
        </NcCheckboxRadioSwitch>

        <template v-if="settings.openaiEnabled">
          <NcTextField
            v-model="settings.openaiApiKey"
            :label="t('talk_bot', 'API Key')"
            :placeholder="t('talk_bot', 'sk-...')"
            type="password"
          />

          <NcSelect
            v-model="settings.openaiModel"
            :options="openaiModels"
            :label="t('talk_bot', 'Model')"
            :placeholder="t('talk_bot', 'Select model')"
          />

          <NcButton
            type="secondary"
            :loading="testingConnection"
            @click="testOpenAIConnection"
          >
            {{ t('talk_bot', 'Test Connection') }}
          </NcButton>
        </template>
      </NcCard>
    </div>

    <!-- Custom Provider Settings Tab (US-008) -->
    <div v-show="activeTab === 'custom'" class="tab-content">
      <NcCard>
        <h3>{{ t('talk_bot', 'Custom Provider Configuration') }}</h3>

        <NcCheckboxRadioSwitch
          v-model="settings.customProviderEnabled"
          type="switch"
        >
          {{ t('talk_bot', 'Enable Custom Provider') }}
        </NcCheckboxRadioSwitch>

        <template v-if="settings.customProviderEnabled">
          <NcTextField
            v-model="settings.customProviderEndpoint"
            :label="t('talk_bot', 'Endpoint URL')"
            :placeholder="t('talk_bot', 'https://api.example.com/v1/chat/completions')"
          />

          <NcTextField
            v-model="settings.customProviderModel"
            :label="t('talk_bot', 'Model Name')"
            :placeholder="t('talk_bot', 'model-name')"
          />

          <div class="headers-section">
            <h4>{{ t('talk_bot', 'Custom Headers (Optional)') }}</h4>
            
            <div class="header-input">
              <NcTextField
                v-model="headerKeyInput"
                :label="t('talk_bot', 'Header Name')"
                :placeholder="t('talk_bot', 'Authorization')"
              />
              <NcTextField
                v-model="headerValueInput"
                :label="t('talk_bot', 'Header Value')"
                :placeholder="t('talk_bot', 'Bearer ...')"
                type="password"
              />
              <NcButton
                type="secondary"
                :disabled="!headerKeyInput.trim() || !headerValueInput.trim()"
                @click="addHeader"
              >
                {{ t('talk_bot', 'Add') }}
              </NcButton>
            </div>

            <div v-if="Object.keys(settings.customProviderHeaders).length > 0" class="headers-list">
              <div
                v-for="(value, key) in settings.customProviderHeaders"
                :key="key"
                class="header-item"
              >
                <span class="header-key">{{ key }}:</span>
                <span class="header-value">***</span>
                <NcButton type="tertiary-no-background" @click="removeHeader(key as string)">
                  ✕
                </NcButton>
              </div>
            </div>
          </div>

          <NcButton
            type="secondary"
            :loading="testingConnection"
            @click="testCustomProviderConnection"
          >
            {{ t('talk_bot', 'Test Connection') }}
          </NcButton>
        </template>
      </NcCard>
    </div>

    <!-- AI Status Dashboard Tab (US-010) -->
    <div v-show="activeTab === 'status'" class="tab-content">
      <NcCard>
        <h3>{{ t('talk_bot', 'AI Status Dashboard') }}</h3>

        <div class="status-dashboard">
          <div class="status-indicator">
            <span
              class="status-dot"
              :class="statusColor"
            ></span>
            <span class="status-label">{{ statusLabel }}</span>
          </div>

          <div class="status-info">
            <div class="info-row">
              <span class="info-label">{{ t('talk_bot', 'Active Provider') }}:</span>
              <span class="info-value">{{ settings.activeProvider || 'none' }}</span>
            </div>
            <div v-if="settings.lastError" class="info-row error">
              <span class="info-label">{{ t('talk_bot', 'Last Error') }}:</span>
              <span class="info-value">{{ settings.lastError }}</span>
            </div>
          </div>

          <div class="status-actions">
            <NcButton
              type="secondary"
              @click="refreshStatus"
            >
              {{ t('talk_bot', 'Refresh Status') }}
            </NcButton>
            <NcButton
              type="primary"
              :disabled="settings.activeProvider === 'none'"
              @click="quickTest"
            >
              {{ t('talk_bot', 'Quick Test') }}
            </NcButton>
          </div>
        </div>
      </NcCard>
    </div>

    <div class="actions">
      <NcButton
        type="primary"
        :loading="saving"
        @click="saveSettings"
      >
        {{ t('talk_bot', 'Save Settings') }}
      </NcButton>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.talk-bot-admin {
  max-width: 800px;
  margin: 0 auto;
  padding: 20px;

  h2 {
    margin-bottom: 24px;
  }

  h3 {
    margin-bottom: 16px;
    font-weight: bold;
  }

  h4 {
    margin-bottom: 12px;
    font-weight: bold;
    font-size: 14px;
  }

  .description {
    color: var(--color-text-maxcontrast);
    margin-bottom: 16px;
  }

  // Tabs
  .tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 24px;
    border-bottom: 1px solid var(--color-border);
    padding-bottom: 8px;
  }

  .tab {
    padding: 8px 16px;
    border: none;
    background: transparent;
    color: var(--color-text-maxcontrast);
    cursor: pointer;
    border-radius: 4px;
    transition: all 0.2s;

    &:hover {
      background: var(--color-background-hover);
    }

    &.active {
      background: var(--color-primary-element);
      color: var(--color-primary-text);
    }
  }

  .tab-content {
    animation: fadeIn 0.2s ease-in-out;
  }

  @keyframes fadeIn {
    from {
      opacity: 0;
      transform: translateY(4px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  // Channel Input
  .channel-input {
    display: flex;
    align-items: flex-end;
    gap: 12px;
    margin-bottom: 16px;

    :deep(.NcTextField) {
      flex: 1;
    }
  }

  .channel-list {
    margin-top: 12px;
  }

  // Headers Section
  .headers-section {
    margin: 16px 0;
    padding: 16px;
    background: var(--color-background-dark);
    border-radius: 8px;
  }

  .header-input {
    display: flex;
    gap: 12px;
    align-items: flex-end;
    margin-bottom: 12px;

    :deep(.NcTextField) {
      flex: 1;
    }
  }

  .headers-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .header-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: var(--color-background);
    border-radius: 4px;
  }

  .header-key {
    font-weight: bold;
    font-family: monospace;
  }

  .header-value {
    flex: 1;
    font-family: monospace;
    color: var(--color-text-maxcontrast);
  }

  // Status Dashboard
  .status-dashboard {
    display: flex;
    flex-direction: column;
    gap: 20px;
  }

  .status-indicator {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    background: var(--color-background-dark);
    border-radius: 8px;
  }

  .status-dot {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    animation: pulse 2s infinite;

    &.green {
      background: #31c148;
      box-shadow: 0 0 8px #31c148;
    }

    &.yellow {
      background: #f5a623;
      box-shadow: 0 0 8px #f5a623;
    }

    &.red {
      background: #e9322d;
      box-shadow: 0 0 8px #e9322d;
    }

    &.gray {
      background: #888;
    }
  }

  @keyframes pulse {
    0%, 100% {
      opacity: 1;
    }
    50% {
      opacity: 0.6;
    }
  }

  .status-label {
    font-size: 16px;
    font-weight: bold;
  }

  .status-info {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .info-row {
    display: flex;
    gap: 8px;

    &.error .info-value {
      color: #e9322d;
    }
  }

  .info-label {
    font-weight: bold;
    min-width: 140px;
  }

  .info-value {
    color: var(--color-text-maxcontrast);
  }

  .status-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
  }

  // Actions
  .actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 24px;
  }

  :deep(.NcCard) {
    margin-bottom: 16px;
  }

  :deep(.NcTextField),
  :deep(.NcNumberInput),
  :deep(.NcSelect) {
    margin-bottom: 16px;
    width: 100%;
    max-width: 400px;
  }

  :deep(.NcCheckboxRadioSwitch) {
    margin-bottom: 16px;
  }
}
</style>
