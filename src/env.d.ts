declare module '*.vue' {
  import type { DefineComponent } from 'vue'
  const component: DefineComponent<{}, {}, any>
  export default component
}

declare const t: (app: string, text: string, params?: object) => string;
declare const n: (app: string, text: string, text_plural: string, count: number, params?: object) => string;

declare module '@nextcloud/dialogs' {
  export function showSuccess(message: string): void;
  export function showError(message: string): void;
  export function showWarning(message: string): void;
  export function showInfo(message: string): void;
}

declare global {
  interface Window {
    _talk_bot_settings?: {
      botUrl: string;
      botToken: string;
      allowedChannels: string[];
      enableAI: boolean;
      aiModel: string;
      maxMessages: number;
    };
  }
}

export {};
