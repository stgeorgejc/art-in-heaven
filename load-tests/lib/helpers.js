import { sleep } from 'k6';

/**
 * Sleep for a random duration between min and max seconds.
 * Simulates human "think time" between actions.
 */
export function thinkTime(minSeconds, maxSeconds) {
  const duration = minSeconds + Math.random() * (maxSeconds - minSeconds);
  sleep(duration);
}

/**
 * Return a random integer between min and max (inclusive).
 */
export function randomIntBetween(min, max) {
  return Math.floor(Math.random() * (max - min + 1)) + min;
}

/**
 * Pick one random element from an array.
 */
export function randomItem(arr) {
  return arr[Math.floor(Math.random() * arr.length)];
}

/**
 * Pick `count` random unique elements from an array.
 * Uses a partial Fisher-Yates shuffle for unbiased selection.
 */
export function randomItems(arr, count) {
  const n = arr.length;
  const resultCount = Math.min(count, n);
  const copy = [...arr];

  for (let i = n - 1; i >= n - resultCount; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [copy[i], copy[j]] = [copy[j], copy[i]];
  }

  return copy.slice(n - resultCount);
}

/**
 * Generate a random whole-dollar bid amount between min and max.
 * The server floor()s to integers, so we only generate integers.
 */
export function randomBidAmount(min, max) {
  return randomIntBetween(min, max);
}

/**
 * Encode an object as application/x-www-form-urlencoded, handling PHP
 * array notation for array values (e.g. art_piece_ids[] = [1,2,3]).
 */
export function encodeFormData(params) {
  const parts = [];
  for (const [key, value] of Object.entries(params)) {
    if (Array.isArray(value)) {
      for (const item of value) {
        parts.push(`${encodeURIComponent(key + '[]')}=${encodeURIComponent(item)}`);
      }
    } else {
      parts.push(`${encodeURIComponent(key)}=${encodeURIComponent(value)}`);
    }
  }
  return parts.join('&');
}
