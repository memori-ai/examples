import { useState, useEffect, useCallback } from 'react'
import Chat, { Bubble, useMessages, MessageProps } from '@chatui/core'
import memoriApiClient from '@memori.ai/memori-api-client'
import '@chatui/core/dist/index.css'
import './App.css'

const client = memoriApiClient()

function App() {
  const { messages, appendMsg, setTyping } = useMessages([])
  const [sessionId, setSessionId] = useState<string>()

  const fetchSession = useCallback(async () => {
    if (sessionId) return

    const { sessionID, currentState, ...resp } = await client.initSession({
      memoriID: '1afe57c6-1b69-4a61-96ea-52bf7b8d158e'
    })

    if (sessionID && currentState && resp.resultCode === 0) {
      setSessionId(sessionID)

      console.log('fetched session', sessionID, currentState)
      if (currentState.emission) {
        appendMsg({
          type: 'text',
          content: { text: currentState.emission }
        })
      }
    }
  }, [appendMsg, sessionId])

  useEffect(() => {
    if (!sessionId) fetchSession()
  }, [sessionId, fetchSession])

  const handleSend = async (type: string, val: string) => {
    if (!sessionId) {
      console.error('No session ID')
      return
    }

    if (type === 'text' && val.trim()) {
      appendMsg({
        type: 'text',
        content: { text: val },
        position: 'right'
      })

      setTyping(true)

      const { currentState, ...resp } = await client.postTextEnteredEvent({
        sessionId,
        text: val
      })

      if (currentState.emission && resp.resultCode === 0)
        appendMsg({
          type: 'text',
          content: { text: currentState.emission }
        })
    }
  }

  function renderMessageContent(msg: MessageProps) {
    const { content } = msg
    return <Bubble content={content.text} />
  }

  return (
    <Chat
      locale="en-US"
      navbar={{ title: 'Parla con Nunzio' }}
      messages={messages}
      renderMessageContent={renderMessageContent}
      onSend={handleSend}
      placeholder="Chiedimi qualcosa..."
    />
  )
}

export default App
