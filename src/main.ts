import { createApp } from 'vue';
import AdminSettings from './components/AdminSettings.vue';

const app = createApp(AdminSettings);
app.mount(document.getElementById('talk-bot-admin-settings')!);
