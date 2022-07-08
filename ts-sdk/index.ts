import memoriApiClient from '@memori.ai/memori-api-client'
import fetch from 'cross-fetch'
import * as readline from 'readline'

const client = memoriApiClient()

let sessionId: string

const handleConversation = () => {
  const commandLineIO = readline.createInterface({
    input: process.stdin,
    output: process.stdout
  })

  commandLineIO.question('You: ', async (question) => {
    if (!sessionId) {
      console.log('No session ID')
      return
    }

    const { currentState, ...resp } = await client.postTextEnteredEvent({
      sessionId,
      text: question
    })

    if (currentState && resp.resultCode === 0) {
      let emission = currentState.emission
      if (emission) console.log(`Nunzio: ${emission}`)
    } else {
      console.error(resp)
    }

    commandLineIO.close()
    handleConversation()
  })
}

;(async () => {
  const { sessionID, currentState, ...resp } = await client.initSession({
    memoriID: '1afe57c6-1b69-4a61-96ea-52bf7b8d158e'
  })

  if (sessionID && currentState && resp.resultCode === 0) {
    sessionId = sessionID

    let emission = currentState.emission
    if (emission) console.log(`Nunzio: ${emission}`)

    handleConversation()
  } else {
    console.error(resp)
  }
})()
