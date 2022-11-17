/**
 * Welcome to Cloudflare Workers! This is your first worker.
 *
 * - Run `wrangler dev src/index.ts` in your terminal to start a development server
 * - Open a browser tab at http://localhost:8787/ to see your worker in action
 * - Run `wrangler publish src/index.ts --name my-worker` to publish your worker
 *
 * Learn more at https://developers.cloudflare.com/workers/
 */

export interface Env {
  // Example binding to KV. Learn more at https://developers.cloudflare.com/workers/runtime-apis/kv/
  // MY_KV_NAMESPACE: KVNamespace;
  //
  // Example binding to Durable Object. Learn more at https://developers.cloudflare.com/workers/runtime-apis/durable-objects/
  // MY_DURABLE_OBJECT: DurableObjectNamespace;
  //
  // Example binding to R2. Learn more at https://developers.cloudflare.com/workers/runtime-apis/r2/
  // MY_BUCKET: R2Bucket;
}

export default {
  async fetch(request: Request, env: Env, ctx: ExecutionContext): Promise<Response> {
    if (request.method !== 'POST') {
      return new Response('Method not allowed', { status: 405 })
    }

    const { headers } = request
    const contentType = headers.get('content-type') || ''

    if (!contentType.includes('application/json')) {
      return new Response('Unsupported content type', { status: 415 })
    }

    const body = await request.json()
    if (!body) {
      return new Response('No params', { status: 422 })
    }

    console.log(body)
    const { intentName, memoriID, culture, utterance, slotValues, contextVars } = body as { [key: string]: any }
    const result = {
      emission:
        culture && culture === 'it-IT'
          ? `Ciao, l'intento ${intentName} sta funzionando e risponde correnttamente alla frase "${utterance}"!`
          : `Hello, intent ${intentName} is working and responds correctly to the utterance "${utterance}"!`,
      preformatted: false,
      conclusive: true,
      media: [],
      contextVarsToSet: {},
      hints: culture && culture === 'it-IT' ? ['Yey!', 'Grande!'] : ['Yey!', 'Great!'],
      tunneling: false
    }

    return new Response(JSON.stringify(result))
  }
}
