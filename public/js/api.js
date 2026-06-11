/**
 * api.js
 *
 * Thin fetch-based HTTP client for the volunteer contracts REST API.
 *
 * All methods return Promises that resolve with the parsed JSON body on
 * success and reject with an Error whose `.message` contains the server's
 * error string.
 *
 * Usage:
 *   import { Api } from './api.js';   // if using modules
 *   Api.workers.list()
 *      .then(data => console.log(data))
 *      .catch(err => console.error(err.message));
 *
 * Because we target vanilla JS without a bundler, everything is attached
 * to the global `window.Api` object instead of using ES modules.
 */

'use strict';

(function (global) {

  // -------------------------------------------------------------------------
  // Core request helper
  // -------------------------------------------------------------------------

  /**
   * Perform an HTTP request and return the parsed JSON body.
   *
   * @param {string} method  GET | POST | PUT | DELETE
   * @param {string} url
   * @param {object|null} body  Request payload (will be JSON-encoded)
   * @returns {Promise<object>}
   */
  async function request(method, url, body = null) {
    const options = {
      method,
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    };

    if (body !== null) {
      options.body = JSON.stringify(body);
    }

    let response;
    try {
      response = await fetch(url, options);
    } catch (networkError) {
      throw new Error(`Network error: ${networkError.message}`);
    }

    let json;
    try {
      json = await response.json();
    } catch {
      throw new Error(`Server returned non-JSON response (HTTP ${response.status}).`);
    }

    if (!response.ok) {
      const msg = Array.isArray(json.error)
        ? json.error.join('\n')
        : (json.error || `HTTP ${response.status}`);
      throw new Error(msg);
    }

    return json;
  }

  // -------------------------------------------------------------------------
  // Workers API
  // -------------------------------------------------------------------------

  const workers = {
    /**
     * List all workers, optionally filtered by search query.
     * @param {string} [q]  Search string
     */
    list(q = '') {
      const qs = q ? `?q=${encodeURIComponent(q)}` : '';
      return request('GET', `/api/workers${qs}`);
    },

    /** Fetch a single worker by id. */
    get(id) {
      return request('GET', `/api/workers/${encodeURIComponent(id)}`);
    },

    /** Create a new worker. */
    create(data) {
      return request('POST', '/api/workers', data);
    },

    /** Replace an existing worker. */
    update(id, data) {
      return request('PUT', `/api/workers/${encodeURIComponent(id)}`, data);
    },

    /** Delete a worker. */
    delete(id) {
      return request('DELETE', `/api/workers/${encodeURIComponent(id)}`);
    },
  };

  // -------------------------------------------------------------------------
  // Contracts API
  // -------------------------------------------------------------------------

  const contracts = {
    /**
     * List all contracts, optionally filtered by status.
     * @param {string} [status]  draft | signed | archived
     */
    list(status = '') {
      const qs = status ? `?status=${encodeURIComponent(status)}` : '';
      return request('GET', `/api/contracts${qs}`);
    },

    /** Fetch a single contract by id. */
    get(id) {
      return request('GET', `/api/contracts/${encodeURIComponent(id)}`);
    },

    /** Create a new contract. */
    create(data) {
      return request('POST', '/api/contracts', data);
    },

    /** Replace an existing contract. */
    update(id, data) {
      return request('PUT', `/api/contracts/${encodeURIComponent(id)}`, data);
    },

    /** Delete a contract. */
    delete(id) {
      return request('DELETE', `/api/contracts/${encodeURIComponent(id)}`);
    },

    /**
     * Generate a preview HTML fragment without saving.
     * @param {object} formData  Raw contract form fields
     */
    preview(formData) {
      return request('POST', '/api/contracts/preview', formData);
    },

    /**
     * Retrieve the rendered preview of a saved contract.
     * @param {string} id
     */
    getPreview(id) {
      return request('GET', `/api/contracts/${encodeURIComponent(id)}/preview`);
    },
  };

  // -------------------------------------------------------------------------
  // Expose globally
  // -------------------------------------------------------------------------

  global.Api = { workers, contracts, _request: request };

}(window));
