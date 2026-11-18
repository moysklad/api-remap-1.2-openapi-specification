import { createServer, type IncomingMessage, type ServerResponse } from 'node:http';
import type { AddressInfo } from 'node:net';

const LOCATION_TARGET_PATH = '/print-result';

/** Один фиксированный ответ стаба. */
interface StubResponse {
    status: number;
    headerName?: string;
    headerValue?: string;
    body?: string;
}

/**
 * Локальный HTTP-стаб с фиксированными ответами и счётчиком обращений к адресу из Location.
 *
 * node:http отправляет имя заголовка в том регистре, в котором его задал тест.
 */
export class LocationStubServer {
    private readonly responses: StubResponse[] = [];
    private hits = 0;
    private lastHeaders: IncomingMessage['headers'] = {};
    private readonly server = createServer((request, response) => this.handle(request, response));

    static async start(): Promise<LocationStubServer> {
        const stub = new LocationStubServer();
        await new Promise<void>((resolve) => stub.server.listen(0, '127.0.0.1', resolve));
        return stub;
    }

    get basePath(): string {
        return `http://127.0.0.1:${this.port}/api/remap/1.2`;
    }

    locationUrl(name: string): string {
        return `http://127.0.0.1:${this.port}${LOCATION_TARGET_PATH}/${name}`;
    }

    /** Количество обращений к адресу, переданному в Location. */
    get locationHits(): number {
        return this.hits;
    }

    /** Заголовки последнего запроса к API (имена в нижнем регистре). */
    get lastRequestHeaders(): IncomingMessage['headers'] {
        return this.lastHeaders;
    }

    /** Ответы отдаются в порядке постановки в очередь, по одному на вызов. */
    enqueue(status: number, headerName?: string, headerValue?: string, body?: string): void {
        this.responses.push({ status, headerName, headerValue, body });
    }

    async close(): Promise<void> {
        this.server.closeAllConnections();
        await new Promise<void>((resolve, reject) => this.server.close((error) => (error ? reject(error) : resolve())));
    }

    private get port(): number {
        return (this.server.address() as AddressInfo).port;
    }

    private handle(request: IncomingMessage, response: ServerResponse): void {
        request.resume();

        if (request.url?.startsWith(LOCATION_TARGET_PATH)) {
            this.hits++;
            response.writeHead(200).end();
            return;
        }

        this.lastHeaders = request.headers;
        const next = this.responses.shift();
        if (next === undefined) {
            response.writeHead(599).end();
            return;
        }

        if (next.headerName !== undefined && next.headerValue !== undefined) {
            response.setHeader(next.headerName, next.headerValue);
        }
        if (next.body !== undefined) {
            response.setHeader('Content-Type', 'application/json;charset=utf-8');
        }
        response.writeHead(next.status).end(next.body);
    }
}
