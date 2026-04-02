/**
 * Chat audio notification utilities.
 *
 * Handles playback of notification sounds with mute state persistence,
 * legacy storage key migration, and browser tab visibility awareness.
 */

/** Primary localStorage key for storing the mute state */
const CHAT_MUTED_STORAGE_KEY = 'chat:muted';

/** Legacy storage keys from previous implementations (for migration) */
const LEGACY_MUTED_STORAGE_KEYS = ['agentflix:chat:sound-muted'];

/** Global scope property name for cross-tab mute state synchronization */
const GLOBAL_MUTED_FLAG = '__CHAT_MUTED__';

/** In-memory cache of Audio instances to avoid recreating them */
const audioCache = new Map<string, HTMLAudioElement>();

/**
 * Plays a notification sound if conditions are met.
 *
 * Sound will NOT play if:
 * - The chat is muted by user preference
 * - The browser tab is currently visible (to avoid intruding)
 * - The URL is empty or invalid
 *
 * @param mp3Url - URL of the MP3 audio file to play
 * @returns True if playback started successfully, false otherwise
 *
 * @example
 * ```typescript
 * await tocarNotificacao('/assets/sounds/new-message.mp3');
 * ```
 */
export async function tocarNotificacao(mp3Url: string): Promise<boolean> {
  const source = typeof mp3Url === 'string' ? mp3Url.trim() : '';
  if (!source || !isBrowser() || isChatMuted() || !isDocumentHidden()) {
    return false;
  }

  const audio = getAudioInstance(source);
  if (!audio) return false;

  audio.currentTime = 0;
  audio.preload = 'auto';

  try {
    const playPromise = audio.play();
    if (playPromise && typeof playPromise.then === 'function') {
      await playPromise;
    }
    return true;
  } catch {
    return false;
  }
}

/**
 * Checks whether chat notification sounds are currently muted.
 *
 * Checks multiple storage locations in order of priority:
 * 1. Current localStorage key
 * 2. Legacy localStorage keys (for migration)
 * 3. Global window scope (for cross-tab synchronization)
 *
 * @returns True if sounds are muted, false otherwise
 */
export function isChatMuted(): boolean {
  const stored = readStoredBoolean(CHAT_MUTED_STORAGE_KEY);
  if (stored !== null) return stored;

  for (const legacyKey of LEGACY_MUTED_STORAGE_KEYS) {
    const legacy = readStoredBoolean(legacyKey);
    if (legacy !== null) return legacy;
  }

  const globalScope = getGlobalScope();
  if (globalScope) {
    const globalValue = globalScope[GLOBAL_MUTED_FLAG];
    const parsed = parseBoolean(globalValue);
    if (parsed !== null) return parsed;
  }

  return false;
}

/**
 * Sets the chat mute state and persists it across all storage locations.
 *
 * Updates current localStorage, legacy keys (for migration), and the
 * global window scope for cross-tab synchronization.
 *
 * @param muted - Whether sounds should be muted
 */
export function setChatMuted(muted: boolean): void {
  if (!isBrowser()) return;
  setStoredBoolean(CHAT_MUTED_STORAGE_KEY, muted);
  LEGACY_MUTED_STORAGE_KEYS.forEach((key) => setStoredBoolean(key, muted));
  const globalScope = getGlobalScope();
  if (globalScope) {
    globalScope[GLOBAL_MUTED_FLAG] = muted;
  }
}

/**
 * Test utility: clears the in-memory audio element cache.
 *
 * @internal - Only for use in unit tests
 */
export function __resetNotificationAudioCacheForTests(): void {
  audioCache.clear();
}

/**
 * Checks if code is running in a browser environment.
 *
 * @returns True if window is defined, false otherwise
 */
function isBrowser(): boolean {
  return typeof window !== 'undefined';
}

/**
 * Checks if the browser tab is currently hidden (not visible to user).
 *
 * Uses the Page Visibility API when available, with fallback to the
 * older hidden property for browser compatibility.
 *
 * @returns True if document is hidden or API is unavailable, false if visible
 */
function isDocumentHidden(): boolean {
  if (typeof document === 'undefined') return false;
  if (typeof document.visibilityState === 'string') {
    return document.visibilityState === 'hidden';
  }
  const doc = document as Document & { hidden?: boolean };
  if (typeof doc.hidden === 'boolean') {
    return doc.hidden;
  }
  return false;
}

/**
 * Gets or creates a cached HTMLAudioElement for the given source URL.
 *
 * @param source - URL of the audio file
 * @returns HTMLAudioElement instance or null if Audio is unavailable
 */
function getAudioInstance(source: string): HTMLAudioElement | null {
  const cached = audioCache.get(source);
  if (cached) return cached;
  if (typeof Audio === 'undefined') return null;

  try {
    const audio = new Audio(source);
    audioCache.set(source, audio);
    return audio;
  } catch {
    return null;
  }
}

/**
 * Safely retrieves the localStorage object.
 *
 * @returns localStorage instance or null if unavailable (e.g., private browsing)
 */
function getLocalStorage(): Storage | null {
  if (!isBrowser()) return null;
  try {
    return window.localStorage;
  } catch {
    return null;
  }
}

/**
 * Gets the global window object cast to a generic record for property access.
 *
 * @returns Window object as a record or null if not in browser
 */
function getGlobalScope(): Record<string, unknown> | null {
  if (!isBrowser()) return null;
  return window as unknown as Record<string, unknown>;
}

/**
 * Reads a boolean value from localStorage, handling parse errors gracefully.
 *
 * @param key - Storage key to read
 * @returns The stored boolean, or null if not found or invalid
 */
function readStoredBoolean(key: string): boolean | null {
  const storage = getLocalStorage();
  if (!storage) return null;
  try {
    const raw = storage.getItem(key);
    return parseBoolean(raw);
  } catch {
    return null;
  }
}

/**
 * Stores a boolean value in localStorage, handling quota/security errors.
 *
 * @param key - Storage key to write
 * @param value - Boolean value to store
 */
function setStoredBoolean(key: string, value: boolean): void {
  const storage = getLocalStorage();
  if (!storage) return;
  try {
    storage.setItem(key, value ? '1' : '0');
  } catch {
    // ignore quota/security errors
  }
}

/**
 * Parses a value into a boolean with flexible input handling.
 *
 * Accepts:
 * - Boolean primitives (returned as-is)
 * - Strings '1', 'true' (case-insensitive) -> true
 * - Strings '0', 'false' (case-insensitive) -> false
 *
 * @param value - Value to parse
 * @returns Parsed boolean or null if not parseable
 */
function parseBoolean(value: unknown): boolean | null {
  if (typeof value === 'boolean') return value;
  if (typeof value === 'string') {
    const normalized = value.trim().toLowerCase();
    if (normalized === '1' || normalized === 'true') return true;
    if (normalized === '0' || normalized === 'false') return false;
  }
  return null;
}
