
// TextGenerateEffect 组件
const TextGenerateEffect = ({
  words,
  className,
  filter = true,
  duration = 0.5,
  isExiting = false,
}) => {
  const { useEffect } = React;
  const motionLib = window.Motion || window.FramerMotion;
  if (!motionLib) {
    console.error("Framer Motion not found");
    return React.createElement("div", null, words);
  }
  const { motion, stagger, useAnimate } = motionLib;
  
  const [scope, animate] = useAnimate();
  let wordsArray = words ? (words.includes(" ") ? words.split(" ") : Array.from(words)) : [];
  
  useEffect(() => {
    if (scope.current) {
      if (isExiting) {
        // 消失动画：逐渐模糊并透明
        animate(
          "span",
          {
            opacity: 0,
            filter: filter ? "blur(10px)" : "none",
          },
          {
            duration: duration ? duration : 1,
            delay: stagger(0.1, { from: "last" }), // 从最后一个字符开始消失
          }
        );
      } else {
        // 进场动画：重置并显示
        const spans = scope.current.querySelectorAll("span");
        spans.forEach(span => {
          span.style.opacity = 0;
          if (filter) span.style.filter = "blur(10px)";
        });

        animate(
          "span",
          {
            opacity: 1,
            filter: filter ? "blur(0px)" : "none",
          },
          {
            duration: duration ? duration : 1,
            delay: stagger(0.15),
          }
        );
      }
    }
  }, [words, isExiting, scope.current]);

  const renderWords = () => {
    return React.createElement(
      motion.div,
      { ref: scope },
      wordsArray.map((word, idx) => {
        return React.createElement(
          motion.span,
          {
            key: word + idx,
            className: "opacity-0",
            style: {
              filter: filter ? "blur(10px)" : "none",
            },
          },
          word + (words.includes(" ") ? " " : "")
        );
      })
    );
  };

  return React.createElement(
    "div",
    { className: window.cn("font-bold", className) },
    React.createElement(
      "div",
      { className: "mt-4" },
      React.createElement(
        "div",
        { className: "leading-snug tracking-wide" },
        renderWords()
      )
    )
  );
};

window.TextGenerateEffect = TextGenerateEffect;
