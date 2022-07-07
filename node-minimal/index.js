const fetch = require("cross-fetch");
const readline = require("readline");

let state = null;

const startConversation = () => {
  fetch("https://engine.memori.ai/memori/v2/session", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      memoriId: "1afe57c6-1b69-4a61-96ea-52bf7b8d158e",
    }),
  })
    .then((response) => response.json())
    .then((result) => {
      state = result;

      if (state.currentState.emission)
        console.log(`Nunzio: ${state.currentState.emission}`);

      handleConversation();
    })
    .catch((error) => console.log("error", error));
};

const handleConversation = () => {
  const commandLineIO = readline.createInterface({
    input: process.stdin,
    output: process.stdout,
  });

  commandLineIO.question("You: ", (question) => {
    fetch(
      `https://engine.memori.ai/memori/v2/TextEnteredEvent/${state.sessionID}`,
      {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          text: question,
        }),
      }
    )
      .then((response) => response.json())
      .then((result) => {
        state = result;

        if (state.currentState.emission)
          console.log(`Nunzio: ${state.currentState.emission}`);
      })
      .catch((error) => console.log("error", error))
      .finally(() => {
        commandLineIO.close();
        handleConversation();
      });
  });
};

startConversation();
